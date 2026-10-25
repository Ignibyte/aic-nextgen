<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\AtomTemplates;
use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\Component;
use AIC\Component\Exception\UnknownComponent;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use Override;

use function array_chunk;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function basename;
use function glob;
use function implode;
use function is_a;
use function str_ends_with;
use function str_starts_with;
use function strtr;

use const ARRAY_FILTER_USE_KEY;

/**
 * The build's template lookup (#98): one `<M>Templates` per module with
 * templates, in parts of four, each matching its components to their
 * generated templates and handing anything else to the next; `ModuleTemplates`
 * starts the chain and the framework's atom templates end it. A module
 * component with no template is refused.
 */
final readonly class TemplatesSource
{
    /**
     * The most components one class matches: each brings its class and its template to the coupling count.
     */
    private const int PART = 4;

    /**
     * A part of the chain, with `{class}`, `{what}`, `{next}` and `{arms}`.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class} implements ComponentTemplates
        {
            /**
             * @param ComponentTemplates $componentTemplates The templates after these.
             */
            public function __construct(
                private ComponentTemplates $componentTemplates = new {next}(),
            ) {
            }

            /**
             * @param Component  $component  A component.
             * @param RenderPass $renderPass The pass rendering it.
             *
             * @throws UnknownComponent When no template renders it.
             *
             * @return string Its markup: {what}
             */
            #[Override]
            public function compose(Component $component, RenderPass $renderPass): string
            {
                return match (true) {
        {arms}
                    default => $this->componentTemplates->compose($component, $renderPass),
                };
            }
        }
        PHP;

    /**
     * The chain's head, with `{first}`.
     */
    private const string HEAD = <<<'PHP'
        final readonly class ModuleTemplates implements ComponentTemplates
        {
            /**
             * @param ComponentTemplates $componentTemplates The chain's first templates.
             */
            public function __construct(
                private ComponentTemplates $componentTemplates = new {first}(),
            ) {
            }

            /**
             * @param Component  $component  A component.
             * @param RenderPass $renderPass The pass rendering it.
             *
             * @throws UnknownComponent When no template renders it.
             *
             * @return string Its markup, from the modules' templates or the framework's atoms.
             */
            #[Override]
            public function compose(Component $component, RenderPass $renderPass): string
            {
                return $this->componentTemplates->compose($component, $renderPass);
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the files.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param string                 $root       The repo root.
     * @param list<ModuleDefinition> $modules    The checked modules, in build order.
     * @param array<string, bool>    $components Whether each template's `render()` takes the pass, by its component
     *                                           class, fully qualified.
     * @param array<string, string>  $templates  Explicit generated template classes by component.
     * @param array<string, string>  $owners     Explicit module owners by component.
     *
     * @throws InvalidArgumentException When a module component has no template, or a class would not fit the gates.
     *
     * @return array<string, string> Each generated class file, by its name under `generated/`.
     */
    public function files(
        string $root,
        array $modules,
        array $components,
        array $templates = [],
        array $owners = [],
    ): array {
        $parts = [];

        foreach ($modules as $module) {
            $this->paired($root, $module, $components);
            $parts = [...$parts, ...$this->moduleParts($module, $components, $owners)];
        }

        $files = ['ModuleTemplates.php' => $this->head($parts === [] ? 'AtomTemplates' : $parts[0][0])];

        foreach ($parts as $index => [$class, $chunk, $module]) {
            $next = $parts[$index + 1][0] ?? 'AtomTemplates';
            $files[$class . '.php'] = $this->part($class, $chunk, $next, $module, $templates);
        }

        return $files;
    }

    /**
     * @param ModuleDefinition      $moduleDefinition A module.
     * @param array<string, bool>   $components       Whether each template's `render()` takes the pass, by component class.
     * @param array<string, string> $owners           Explicit module owners by component.
     *
     * @return list<array{string, array<string, bool>, string}> Its parts of the chain: each class, the components it
     *                                                          matches and the module's name.
     */
    private function moduleParts(ModuleDefinition $moduleDefinition, array $components, array $owners): array
    {
        $owned = array_filter(
            $components,
            static fn (string $component): bool => str_starts_with($component, $moduleDefinition->namespace)
                || ($owners[$component] ?? '') === $moduleDefinition->name,
            ARRAY_FILTER_USE_KEY,
        );
        $parts = [];

        foreach (array_chunk($owned, self::PART, true) as $index => $chunk) {
            $suffix = $index === 0
                ? ''
                : 'Part' . ($index + 1);
            $parts[] = [$moduleDefinition->studlyName() . 'Templates' . $suffix, $chunk, $moduleDefinition->name];
        }

        return $parts;
    }

    /**
     * @param string              $root             The repo root.
     * @param ModuleDefinition    $moduleDefinition A module.
     * @param array<string, bool> $components       Every template's component class, as keys.
     *
     * @throws InvalidArgumentException When a component of the module has no template.
     */
    private function paired(string $root, ModuleDefinition $moduleDefinition, array $components): void
    {
        $files = glob($root . '/modules/' . $moduleDefinition->name . '/src/Component/*.php');

        foreach ($files === false ? [] : $files as $file) {
            $class = $moduleDefinition->namespace . 'Component\\' . basename($file, '.php');

            if (!$this->unpaired($class, $components)) {
                continue;
            }

            throw new InvalidArgumentException($class . ' is a component with no template: add modules/' . $moduleDefinition->name
                . '/templates/components/' . basename($file, '.php') . '.html.php.');
        }
    }

    /**
     * @param string              $class      A class in a module's `src/Component/`.
     * @param array<string, bool> $components Every template's component class, as keys.
     *
     * @return bool Whether it is a component, not a generated template, and no template names it.
     */
    private function unpaired(string $class, array $components): bool
    {
        return !str_ends_with($class, 'Template') && is_a($class, Component::class, true) && !array_key_exists($class, $components);
    }

    /**
     * @param string $first The chain's first class.
     *
     * @throws InvalidArgumentException When it would not fit the gates.
     *
     * @return string `ModuleTemplates`, the chain's head.
     */
    private function head(string $first): string
    {
        $body = strtr(self::HEAD, ['{first}' => $first]);
        $classes = [ComponentTemplates::class, Component::class, RenderPass::class, UnknownComponent::class, Override::class];

        if ($first === 'AtomTemplates') {
            $classes[] = AtomTemplates::class;
        }

        $docblock = $this->phpClassFile->generated('the module templates', 'the head of the template lookup (#98).');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param string                $class      The part's class.
     * @param array<string, bool>   $components The components it matches: whether each template takes the pass.
     * @param string                $next       The class after it.
     * @param string                $module     The module's name.
     * @param array<string, string> $templates  Explicit generated classes by component.
     *
     * @throws InvalidArgumentException When it would not fit the gates.
     *
     * @return string The part's class file.
     */
    private function part(string $class, array $components, string $next, string $module, array $templates): string
    {
        $body = strtr(self::TEMPLATE, [
            '{arms}' => $this->arms($components, $templates),
            '{class}' => $class,
            '{next}' => $next,
            '{what}' => 'the ' . $module . " module's components, then the next.",
        ]);
        $mapped = static fn (string $component): string => $templates[$component] ?? $component . 'Template';
        $classes = [
            ComponentTemplates::class,
            Component::class,
            RenderPass::class,
            UnknownComponent::class,
            Override::class,
            ...array_keys($components),
            ...array_map($mapped, array_keys($components)),
            ...($next === 'AtomTemplates' ? [AtomTemplates::class] : []),
        ];
        $docblock = $this->phpClassFile->generated('modules/' . $module . '/templates', "the module's template lookup (#98).");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param array<string, bool>   $components The components a part matches: whether each template takes the pass.
     * @param array<string, string> $templates  Explicit generated classes by component.
     *
     * @return string Its match arms, one per component.
     */
    private function arms(array $components, array $templates): string
    {
        $arms = [];

        foreach ($components as $component => $takesPass) {
            $short = $this->phpClassFile->short($component);
            $template = $this->phpClassFile->short($templates[$component] ?? $component . 'Template');
            $arms[] = '            $component instanceof ' . $short . ' => (new ' . $template . '())->render($component'
                . ($takesPass ? ', $renderPass' : '') . '),';
        }

        return implode("\n", $arms);
    }
}
