<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Module\Contracts\ModuleTemplate;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\PresentationInventory;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;
use ReflectionClass;

use function array_diff;
use function array_fill_keys;
use function array_intersect_key;
use function array_map;
use function class_exists;
use function implode;
use function is_a;
use function realpath;
use function str_starts_with;
use function strrpos;
use function strtolower;
use function substr;

/** Plan and validate the complete authored presentation before any output is published. */
final readonly class PresentationBuild
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param list<ModuleDefinition> $modules The actual validated module declarations.
     *
     * @throws InvalidArgumentException When a presentation declaration is invalid, ambiguous or absent for an authored tpl source.
     *
     * @return PresentationInventory The complete checked inputs, without generated or public writes.
     */
    public function plan(array $modules): PresentationInventory
    {
        $templates = $this->componentBindings($modules);
        $theme = (new ThemeDeclarations($this->root))->read($modules);

        if ($theme !== null) {
            $templates = [...$templates, $theme->html, $theme->page];
        }

        $this->claims($templates, $modules);

        return new PresentationInventory($templates, $theme);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The declaring module.
     * @param string           $component        Its input class.
     *
     * @throws InvalidArgumentException When it is not a canonical final Component from the module's source.
     *
     * @return class-string<Component> The checked input identity.
     */
    public function component(ModuleDefinition $moduleDefinition, string $component): string
    {
        if (!class_exists($component) || !is_a($component, Component::class, true)) {
            throw new InvalidArgumentException($component . ': declared input must be a component data class.');
        }

        $reflectionClass = new ReflectionClass($component);

        if ($reflectionClass->getName() !== $component) {
            throw new InvalidArgumentException($component . ': declared input must use its canonical class identity.');
        }

        $this->owned($moduleDefinition, $reflectionClass);

        return $component;
    }

    /**
     * @param list<TemplateBinding>  $templates The complete explicit template inventory.
     * @param list<ModuleDefinition> $modules   The modules in dependency order.
     *
     * @throws InvalidArgumentException When a source, input or generated identity repeats, or a tpl file is undeclared.
     */
    private function claims(array $templates, array $modules): void
    {
        $claimed = [];

        foreach ($templates as $template) {
            $claims = array_fill_keys($this->identities($template), $template->source);

            if (array_intersect_key($claimed, $claims) !== []) {
                throw new InvalidArgumentException($template->source . ': duplicate presentation source, input or generated identity.');
            }

            $claimed += $claims;
        }

        $this->undeclared($templates, $modules);
    }

    /**
     * @param TemplateBinding $templateBinding One explicit template binding.
     *
     * @return list<string> Independently unique source, input, output path and PHP class claims.
     */
    private function identities(TemplateBinding $templateBinding): array
    {
        return [
            'source:' . $templateBinding->source,
            'input:' . strtolower($templateBinding->component),
            'output:' . $templateBinding->generated,
            'class:' . strtolower($templateBinding->templateClass),
        ];
    }

    /**
     * @param list<TemplateBinding>  $templates The only selected tpl sources.
     * @param list<ModuleDefinition> $modules   The modules owning authored component sources.
     *
     * @throws InvalidArgumentException When any observed authored tpl file has no explicit declaration.
     */
    private function undeclared(array $templates, array $modules): void
    {
        $declaredPaths = new DeclaredPaths($this->root);
        $files = $declaredPaths->templates('themes');

        foreach ($modules as $module) {
            $files = [...$files, ...$declaredPaths->templates('modules/' . $module->name . '/templates')];
        }

        $sources = array_map(static fn (TemplateBinding $templateBinding): string => $templateBinding->source, $templates);
        $undeclared = array_diff($files, $sources);

        if ($undeclared !== []) {
            throw new InvalidArgumentException('Undeclared presentation templates: ' . implode(', ', $undeclared) . '.');
        }
    }

    /**
     * @param list<ModuleDefinition> $modules Modules in dependency order.
     *
     * @throws InvalidArgumentException When a component or its authored source is not owned and valid.
     *
     * @return list<TemplateBinding> The explicit module component bindings.
     */
    private function componentBindings(array $modules): array
    {
        $bindings = [];

        foreach ($modules as $module) {
            foreach ($module->declared(ModuleTemplate::class) as $declaration) {
                $bindings[] = $this->binding($module, $declaration);
            }
        }

        return $bindings;
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The declaring module.
     * @param ReflectionClass<object> $reflectionClass  The existing component data class.
     *
     * @throws InvalidArgumentException When its canonical identity or actual source is foreign.
     */
    private function owned(ModuleDefinition $moduleDefinition, ReflectionClass $reflectionClass): void
    {
        $component = $reflectionClass->getName();
        $base = realpath($this->root . '/modules/' . $moduleDefinition->name . '/src');
        $file = $reflectionClass->getFileName();

        if (!$reflectionClass->isFinal() || !$moduleDefinition->owns($component)) {
            throw new InvalidArgumentException($component . ': declared input must be the owning module canonical final class.');
        }

        if ($base === false || $file === false || !str_starts_with($file, $base . '/')) {
            throw new InvalidArgumentException($component . ': declared input must live inside its owning module source.');
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The owning module.
     * @param ModuleTemplate   $moduleTemplate   The explicitly paired component and source.
     *
     * @throws InvalidArgumentException When its input or template path is invalid.
     *
     * @return TemplateBinding Its deterministic input and generated output identities.
     */
    private function binding(ModuleDefinition $moduleDefinition, ModuleTemplate $moduleTemplate): TemplateBinding
    {
        $component = $this->component($moduleDefinition, $moduleTemplate->component);

        if (!str_starts_with($moduleTemplate->template, 'templates/components/')) {
            throw new InvalidArgumentException($moduleTemplate->template . ': component templates live under templates/components/.');
        }

        $source = (new DeclaredPaths($this->root))->file('modules/' . $moduleDefinition->name, $moduleTemplate->template);
        $short = substr($component, (int) strrpos($component, '\\') + 1);
        $template = $moduleDefinition->studlyName() . $short . 'Template';

        return new TemplateBinding(
            $moduleDefinition->name,
            $component,
            $source,
            'generated/templates/' . $template . '.php',
            'App\\Generated\\Templates\\' . $template,
            TemplateRole::Component,
        );
    }
}
