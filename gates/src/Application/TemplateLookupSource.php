<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\Component;
use AIC\Component\Exception\UnknownComponent;
use InvalidArgumentException;
use Override;

use function array_keys;
use function array_values;
use function implode;
use function strtr;

/**
 * Emit bounded lookup classes for the theme's and the library's templates (#98, #155): an exact `instanceof` match per
 * component, each calling a method that requires the component's escaped template.
 */
final readonly class TemplateLookupSource
{
    /** A lookup part, with an optional next lookup and explicit match arms. */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class} implements ComponentTemplates
        {
        {constructor}    /**
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
                    default => {fallback},
                };
            }

        {methods}}
        PHP;

    /** A named lookup head. */
    private const string HEAD = <<<'PHP'
        final readonly class {class} implements ComponentTemplates
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
             * @return string {result}
             */
            #[Override]
            public function compose(Component $component, RenderPass $renderPass): string
            {
                return $this->componentTemplates->compose($component, $renderPass);
            }
        }
        PHP;

    /** The optional next lookup in a nonterminal chain part. */
    private const string CONSTRUCTOR = <<<'PHP'
            /**
             * @param ComponentTemplates $componentTemplates The templates after these.
             */
            public function __construct(
                private ComponentTemplates $componentTemplates = new {next}(),
            ) {
            }
        PHP;

    /**
     * @param PhpClassFile          $phpClassFile          Emits checked class source.
     * @param TemplateRequireSource $templateRequireSource Writes each template's render method.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private TemplateRequireSource $templateRequireSource = new TemplateRequireSource(),
    ) {
    }

    /**
     * @param string $namespace The generated namespace.
     * @param string $class     The declared head class.
     * @param string $first     The first lookup class, fully qualified.
     * @param string $from      The source identity for provenance.
     * @param string $result    The render method's result contract.
     *
     * @throws InvalidArgumentException When the emitted class would exceed source limits.
     *
     * @return string The lookup head class source.
     */
    public function head(string $namespace, string $class, string $first, string $from, string $result): string
    {
        $body = strtr(self::HEAD, ['{class}' => $class, '{first}' => $this->phpClassFile->short($first), '{result}' => $result]);
        $classes = [ComponentTemplates::class, Component::class, RenderPass::class, UnknownComponent::class, Override::class, $first];
        $docblock = $this->phpClassFile->generated($from, 'the head of the template lookup (#98).');

        return $this->phpClassFile->file($namespace, $classes, [], $docblock, $body);
    }

    /**
     * @param string                $namespace The declared generated namespace.
     * @param string                $class     The declared lookup class.
     * @param array<string, string> $templates Each component's escaped template, relative to the lookup's folder, by
     *                                         its class.
     * @param string|null           $next      The next lookup, fully qualified; null terminates with refusal.
     * @param string                $from      The source identity for provenance.
     * @param string                $what      The rendered component group.
     * @param array<string, string> $adapters  Each component a generated adapter renders, the adapter's class by the
     *                                         component's (the theme's document and page, #154).
     *
     * @throws InvalidArgumentException When a class cannot fit the normal source limits.
     *
     * @return string A lookup part with no runtime discovery or class-name guessing.
     */
    public function part(
        string $namespace,
        string $class,
        array $templates,
        ?string $next,
        string $from,
        string $what,
        array $adapters = [],
    ): string {
        $constructor = $next === null
            ? ''
            : strtr(self::CONSTRUCTOR, ['{next}' => $this->phpClassFile->short($next)]) . "\n\n";
        $body = strtr(self::TEMPLATE, [
            '{arms}' => $this->arms($templates, $adapters),
            '{class}' => $class,
            '{constructor}' => $constructor,
            '{fallback}' => $next === null
                ? 'throw new UnknownComponent($component::class)'
                : '$this->componentTemplates->compose($component, $renderPass)',
            '{methods}' => $this->methods($templates),
            '{what}' => $what,
        ]);
        $classes = [...$this->classes($templates, $next), ...array_keys($adapters), ...array_values($adapters)];
        $docblock = $this->phpClassFile->generated($from, 'the template lookup (#98).');

        return $this->phpClassFile->file($namespace, $classes, [], $docblock, $body);
    }

    /**
     * @param array<string, string> $templates The components in a lookup part, by class.
     * @param string|null           $next      The next lookup class, if any.
     *
     * @return list<string> Classes used by the emitted lookup.
     */
    private function classes(array $templates, ?string $next): array
    {
        return [
            ComponentTemplates::class,
            Component::class,
            RenderPass::class,
            UnknownComponent::class,
            Override::class,
            ...array_keys($templates),
            ...($next === null ? [] : [$next]),
        ];
    }

    /**
     * @param array<string, string> $templates The components in a bounded lookup part, by class.
     * @param array<string, string> $adapters  The adapters, by the class each renders.
     *
     * @return string Typed match arms: the adapters', then each component's calling its render method.
     */
    private function arms(array $templates, array $adapters): string
    {
        $arms = [];

        foreach ($adapters as $component => $adapter) {
            $arms[] = '            $component instanceof ' . $this->phpClassFile->short($component) . ' => (new '
                . $this->phpClassFile->short($adapter) . '())->render($component, $renderPass),';
        }

        foreach (array_keys($templates) as $component) {
            $arms[] = '            $component instanceof ' . $this->phpClassFile->short($component) . ' => $this->'
                . $this->templateRequireSource->name($component) . '($component, $renderPass),';
        }

        return implode("\n", $arms);
    }

    /**
     * @param array<string, string> $templates Each component's escaped template, relative to the lookup's folder.
     *
     * @return string One render method per component, in declaration order.
     */
    private function methods(array $templates): string
    {
        $methods = [];

        foreach ($templates as $component => $path) {
            $methods[] = $this->templateRequireSource->method($component, $path);
        }

        return implode("\n", $methods);
    }
}
