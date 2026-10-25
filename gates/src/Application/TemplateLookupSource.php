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
use function array_map;
use function implode;
use function strtr;

/** Emit bounded lookup classes for explicit module and library template inventories. */
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
        }
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

    /** @param PhpClassFile $phpClassFile Emits checked class source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
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
     * @param string                $namespace  The declared generated namespace.
     * @param string                $class      The declared lookup class.
     * @param array<string, bool>   $components Each component's render-pass requirement.
     * @param array<string, string> $templates  Each component's exact compiled class.
     * @param string|null           $next       The next lookup, fully qualified; null terminates with refusal.
     * @param string                $from       The source identity for provenance.
     * @param string                $what       The rendered component group.
     *
     * @throws InvalidArgumentException When a class cannot fit the normal source limits.
     *
     * @return string A lookup part with no runtime discovery or class-name guessing.
     */
    public function part(
        string $namespace,
        string $class,
        array $components,
        array $templates,
        ?string $next,
        string $from,
        string $what,
    ): string {
        $constructor = $next === null
            ? ''
            : strtr(self::CONSTRUCTOR, ['{next}' => $this->phpClassFile->short($next)]) . "\n\n";
        $body = strtr(self::TEMPLATE, [
            '{arms}' => $this->arms($components, $templates),
            '{class}' => $class,
            '{constructor}' => $constructor,
            '{fallback}' => $next === null
                ? 'throw new UnknownComponent($component::class)'
                : '$this->componentTemplates->compose($component, $renderPass)',
            '{what}' => $what,
        ]);
        $classes = $this->classes($components, $templates, $next);
        $docblock = $this->phpClassFile->generated($from, 'the template lookup (#98).');

        return $this->phpClassFile->file($namespace, $classes, [], $docblock, $body);
    }

    /**
     * @param array<string, bool>   $components The component identities in a lookup part.
     * @param array<string, string> $templates  Their compiled identities.
     * @param string|null           $next       The next lookup class, if any.
     *
     * @return list<string> Classes used by the emitted lookup.
     */
    private function classes(array $components, array $templates, ?string $next): array
    {
        return [ComponentTemplates::class, Component::class, RenderPass::class, UnknownComponent::class, Override::class,
            ...array_keys($components),
            ...array_map(static fn (string $component): string => $templates[$component], array_keys($components)),
            ...($next === null ? [] : [$next])];
    }

    /**
     * @param array<string, bool>   $components The components in a bounded lookup part.
     * @param array<string, string> $templates  Their explicitly declared output classes.
     *
     * @return string Typed match arms in declaration order.
     */
    private function arms(array $components, array $templates): string
    {
        $arms = [];

        foreach ($components as $component => $takesPass) {
            $short = $this->phpClassFile->short($component);
            $template = $this->phpClassFile->short($templates[$component]);
            $arms[] = '            $component instanceof ' . $short . ' => (new ' . $template . '())->render($component'
                . ($takesPass ? ', $renderPass' : '') . '),';
        }

        return implode("\n", $arms);
    }
}
