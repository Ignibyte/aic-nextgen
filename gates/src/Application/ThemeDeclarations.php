<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use AIC\Component\Domain\ThemeLayout;
use AIC\Module\Contracts\ModuleTheme;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

use function array_keys;
use function array_map;
use function in_array;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function ucwords;

/** Validate the site module's one explicitly selected theme and its authored typed region data. */
final readonly class ThemeDeclarations
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param list<ModuleDefinition> $modules Modules in dependency order.
     *
     * @throws InvalidArgumentException When selection, ownership, files or typed regions are invalid.
     *
     * @return ThemeBinding|null The selected theme, null when no module selects a theme.
     */
    public function read(array $modules): ?ThemeBinding
    {
        $selected = null;

        foreach ($modules as $module) {
            $selected = $this->selection($module, $selected);
        }

        return $selected;
    }

    /**
     * @param ModuleDefinition  $moduleDefinition One module in dependency order.
     * @param ThemeBinding|null $themeBinding     The selection made so far.
     *
     * @throws InvalidArgumentException When more than one theme is selected or a foreign module selects it.
     *
     * @return ThemeBinding|null The unique selection so far.
     */
    private function selection(ModuleDefinition $moduleDefinition, ?ThemeBinding $themeBinding): ?ThemeBinding
    {
        foreach ($moduleDefinition->declared(ModuleTheme::class) as $moduleTheme) {
            if ($moduleDefinition->name !== 'site' || $themeBinding !== null) {
                throw new InvalidArgumentException('Exactly one site module declaration selects the active theme.');
            }

            $themeBinding = $this->binding($moduleDefinition, $moduleTheme);
        }

        return $themeBinding;
    }

    /**
     * @param ModuleDefinition $moduleDefinition The site module.
     * @param ModuleTheme      $moduleTheme      Its selected theme.
     *
     * @throws InvalidArgumentException When names, source paths or page data are invalid.
     *
     * @return ThemeBinding The validated document and page inputs.
     */
    private function binding(ModuleDefinition $moduleDefinition, ModuleTheme $moduleTheme): ThemeBinding
    {
        if (preg_match('/^[a-z][a-z0-9_-]*\\z/', $moduleTheme->name) !== 1) {
            throw new InvalidArgumentException($moduleTheme->name . ': a theme needs a portable lowercase name.');
        }

        $this->regions($moduleTheme);
        $component = (new PresentationBuild($this->root))->component($moduleDefinition, $moduleTheme->pageData);
        $this->pageData($component, $moduleTheme);

        return new ThemeBinding(
            $moduleDefinition->name,
            $moduleTheme,
            $this->template($moduleTheme, $moduleTheme->html, ThemeLayout::class, TemplateRole::Html),
            $this->template($moduleTheme, $moduleTheme->page, $component, TemplateRole::Page),
        );
    }

    /**
     * @param ModuleTheme $moduleTheme The selected region schema.
     *
     * @throws InvalidArgumentException When property or region names are invalid, absent or repeated.
     */
    private function regions(ModuleTheme $moduleTheme): void
    {
        if ($moduleTheme->regions === []) {
            throw new InvalidArgumentException($moduleTheme->name . ': a declared page needs named typed regions.');
        }

        $names = [];

        foreach ($moduleTheme->regions as $property => $region) {
            $this->regionName($property, $region, $names);

            $names[] = $region;
        }
    }

    /**
     * @param string       $property One authored page property.
     * @param string       $region   Its stable region identity.
     * @param list<string> $names    Identities already claimed by this page.
     *
     * @throws InvalidArgumentException When a property or region name is invalid or repeated.
     */
    private function regionName(string $property, string $region, array $names): void
    {
        if (preg_match('/^[a-z][A-Za-z0-9]*\\z/', $property) !== 1) {
            throw new InvalidArgumentException($property . ': a page region property uses a lower-camel identifier.');
        }

        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*\\z/', $region) !== 1 || in_array($region, $names, true)) {
            throw new InvalidArgumentException($region . ': invalid or duplicate declared region name.');
        }
    }

    /**
     * @param class-string $component   The actual authored page data class.
     * @param ModuleTheme  $moduleTheme Its explicit region-to-property schema.
     *
     * @throws InvalidArgumentException When constructor or public property types do not match the complete schema.
     */
    private function pageData(string $component, ModuleTheme $moduleTheme): void
    {
        $reflectionClass = new ReflectionClass($component);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            throw new InvalidArgumentException($component . ': readonly page data constructor must match declared regions in order.');
        }

        $parameters = $constructor->getParameters();
        $names = array_map(static fn (ReflectionParameter $reflectionParameter): string => $reflectionParameter->getName(), $parameters);

        if (!$reflectionClass->isReadOnly() || $names !== array_keys($moduleTheme->regions)) {
            throw new InvalidArgumentException($component . ': readonly page data constructor must match declared regions in order.');
        }

        foreach ($parameters as $parameter) {
            $this->regionParameter($reflectionClass, $parameter);
        }
    }

    /**
     * @param ReflectionClass<object> $reflectionClass     The authored page data class.
     * @param ReflectionParameter     $reflectionParameter One declared region input.
     *
     * @throws InvalidArgumentException When it is not a public promoted nonnullable Region property.
     */
    private function regionParameter(ReflectionClass $reflectionClass, ReflectionParameter $reflectionParameter): void
    {
        $type = $reflectionParameter->getType();

        if (
            !$reflectionParameter->isPromoted()
            || !$type instanceof ReflectionNamedType
            || $type->allowsNull()
            || $type->getName() !== Region::class
        ) {
            throw new InvalidArgumentException(
                $reflectionParameter->getName() . ': page inputs are promoted nonnullable Region properties.',
            );
        }

        $this->publicRegion($reflectionClass, $reflectionParameter);
    }

    /**
     * @param ReflectionClass<object> $reflectionClass     The page data class.
     * @param ReflectionParameter     $reflectionParameter One checked promoted Region input.
     *
     * @throws InvalidArgumentException When its public property is absent or inaccessible.
     */
    private function publicRegion(ReflectionClass $reflectionClass, ReflectionParameter $reflectionParameter): void
    {
        try {
            $property = $reflectionClass->getProperty($reflectionParameter->getName());
        } catch (ReflectionException $reflectionException) {
            throw new InvalidArgumentException(
                $reflectionParameter->getName() . ': a declared region property is missing.',
                0,
                $reflectionException,
            );
        }

        if (!$property->isPublic()) {
            throw new InvalidArgumentException($reflectionParameter->getName() . ': a page region property must be public.');
        }
    }

    /**
     * @param ModuleTheme             $moduleTheme  The selected theme.
     * @param string                  $relative     Its explicitly declared document or page source.
     * @param class-string<Component> $component    The validated typed input class.
     * @param TemplateRole            $templateRole Its transparent rendering role.
     *
     * @throws InvalidArgumentException When a source is missing or outside the theme layouts.
     *
     * @return TemplateBinding Its deterministic output identity.
     */
    private function template(
        ModuleTheme $moduleTheme,
        string $relative,
        string $component,
        TemplateRole $templateRole,
    ): TemplateBinding {
        if (!str_starts_with($relative, 'layouts/')) {
            throw new InvalidArgumentException($relative . ': theme document and page templates live under layouts/.');
        }

        $source = (new DeclaredPaths($this->root))->file('themes/' . $moduleTheme->name, $relative);
        $class = 'Theme' . str_replace(['_', '-'], '', ucwords($moduleTheme->name, '_-')) . $templateRole->name . 'Template';

        return new TemplateBinding(
            'site',
            $component,
            $source,
            'generated/templates/' . $class . '.php',
            'App\\Generated\\Templates\\' . $class,
            $templateRole,
        );
    }
}
