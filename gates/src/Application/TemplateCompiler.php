<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateClass;
use Gates\Domain\TemplateMethods;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function addcslashes;
use function array_map;
use function array_merge;
use function array_reverse;
use function lcfirst;
use function max;
use function mb_str_split;
use function preg_split;
use function str_contains;
use function str_ends_with;
use function str_pad;
use function strlen;
use function substr;
use function substr_count;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Compiles a component template's nodes (#36) into a final readonly class
 * whose `render()` gives the component's Html. The output passes every gate
 * as it is written: each run of output is a part method of at most twelve
 * lines, chained to the next; a condition is a ternary between two parts; a
 * loop maps its typed items through a part. Each component sits between key
 * markers, and each condition and loop between morph markers (#37), so the
 * browser runtime morphs them as units. A template that needs more part
 * methods or lines than one class may hold is refused.
 */
final readonly class TemplateCompiler
{
    /**
     * The lines of pieces one part method concatenates.
     */
    private const int LINES = 12;

    /**
     * The part methods one class holds, beside `render()`.
     */
    private const int PARTS = 14;

    /**
     * The lines one class holds.
     */
    private const int CLASS_LINES = 300;

    /**
     * The literal that closes a condition's or a loop's block markers, rendered even when empty (protocol 2.3).
     */
    private const string BLOCK_END = "'<!--/t:b-->'";

    /**
     * The characters of text one literal holds.
     */
    private const int TEXT = 80;

    /**
     * @param TemplateImports $templateImports Writes the generated file's opening and imports.
     * @param TemplateLoops   $templateLoops   Writes the parts that run a template's loops.
     */
    public function __construct(
        private TemplateImports $templateImports = new TemplateImports(),
        private TemplateLoops $templateLoops = new TemplateLoops(),
    ) {
    }

    /**
     * @param list<TemplateNode> $nodes     The template's nodes.
     * @param string             $namespace The component's namespace.
     * @param string             $component The component's class name.
     *
     * @throws InvalidArgumentException When the template is too big for one class.
     *
     * @return TemplateClass The source of `<component>Template`, in the same namespace, and whether its `render()`
     *                       takes the render pass.
     */
    public function compile(array $nodes, string $namespace, string $component): TemplateClass
    {
        $variable = lcfirst($component);
        $templateMethods = new TemplateMethods($component . '.html.php', $variable);
        $scope = [$component . ' $' . $variable, 'RenderPass $renderPass'];
        $first = $templateMethods->call($this->sequence($nodes, $scope, $templateMethods));
        $takesPass = str_contains($first, '$renderPass');
        $parts = $templateMethods->source();
        $class = $this->templateImports->head($nodes, $namespace, $parts . ($takesPass ? 'RenderPass $renderPass' : ''))
            . $this->declaration($component, $variable, $takesPass, $parts) . '        return ' . $first . ";\n    }\n\n" . $parts . "}\n";

        if ($templateMethods->count() > self::PARTS || substr_count($class, "\n") > self::CLASS_LINES) {
            throw new InvalidArgumentException('too big for one class (' . $templateMethods->count()
                . ' part methods, ' . substr_count($class, "\n") . ' lines): move some of it into components');
        }

        return new TemplateClass($class, $takesPass);
    }

    /**
     * @param list<TemplateNode> $nodes           A run of nodes.
     * @param list<string>       $scope           The variables in scope, `Type $var`, outermost first.
     * @param TemplateMethods    $templateMethods The class's part methods.
     *
     * @return int The number of the part method that renders the run; public for the loop parts' runs.
     */
    public function sequence(array $nodes, array $scope, TemplateMethods $templateMethods): int
    {
        $pieces = [];

        foreach ($nodes as $node) {
            $pieces = array_merge($pieces, $this->pieces($node, $scope, $templateMethods));
        }

        return $this->chain($pieces === [] ? ["''"] : $pieces, $scope, $templateMethods);
    }

    /**
     * @param string $component The component's class name.
     * @param string $variable  Its variable.
     * @param bool   $takesPass Whether `render()` takes the render pass.
     * @param string $parts     The part methods' source.
     *
     * @return string The class docblock and declaration, then `render()` up to its body.
     */
    private function declaration(string $component, string $variable, bool $takesPass, string $parts): string
    {
        $width = max(strlen($component), $takesPass ? strlen('RenderPass') : 0);
        $nameWidth = max(strlen($variable) + 1, $takesPass ? strlen('$renderPass') : 0);
        $docblock = '     * @param ' . str_pad($component, $width) . ' ' . str_pad('$' . $variable, $nameWidth) . " The component.\n"
            . ($takesPass ? '     * @param ' . str_pad('RenderPass', $width) . ' ' . str_pad('$renderPass', $nameWidth)
                . " The pass that writes its children.\n" : '');

        return "/**\n * " . $component . "'s markup, compiled from " . $component
            . ".html.php by composer run build (#97, #98):\n * edit the template and run composer run build, never this file.\n */\n"
            . 'final readonly class ' . $component . "Template\n{\n"
            . (str_contains($parts, 'self::text(') ? "    use RendersMarkup;\n\n" : '')
            . "    /**\n" . $docblock . "     *\n"
            . $this->renderThrows($takesPass)
            . "     * @return string Its markup.\n     */\n"
            . '    public function render(' . $component . ' $' . $variable . ($takesPass ? ', RenderPass $renderPass' : '')
            . "): string\n    {\n";
    }

    /**
     * @param bool $takesPass Whether rendering writes child components.
     *
     * @return string The exception contract for a render pass, empty otherwise.
     */
    private function renderThrows(bool $takesPass): string
    {
        return $takesPass
            ? "     * @throws UnknownComponent When a child has no registered template.\n     *\n"
            : '';
    }

    /**
     * @param list<string>    $pieces          The expressions of a run, at least one.
     * @param list<string>    $scope           The variables in scope.
     * @param TemplateMethods $templateMethods The class's part methods.
     *
     * @return int The number of the first part method of the chain that concatenates them.
     */
    private function chain(array $pieces, array $scope, TemplateMethods $templateMethods): int
    {
        $next = -1;

        foreach (array_reverse($this->groups($pieces)) as $group) {
            $next = $templateMethods->add($scope, $next === -1 ? $group : [...$group, $templateMethods->call($next)]);
        }

        return $next;
    }

    /**
     * @param TemplateNode    $templateNode    A node.
     * @param list<string>    $scope           The variables in scope.
     * @param TemplateMethods $templateMethods The class's part methods.
     *
     * @return list<string> The expressions it concatenates.
     */
    private function pieces(TemplateNode $templateNode, array $scope, TemplateMethods $templateMethods): array
    {
        return match ($templateNode->kind) {
            TemplateNodeKind::Text => $this->texts($templateNode->code),
            TemplateNodeKind::Escaped => ['self::text(' . $templateNode->code . ')'],
            TemplateNodeKind::Raw => [$templateNode->code],
            TemplateNodeKind::Component => $this->component($templateNode),
            TemplateNodeKind::Condition => ["'<!--t:b-->'", $this->condition($templateNode, $scope, $templateMethods), self::BLOCK_END],
            TemplateNodeKind::ElseIf => [$this->condition($templateNode, $scope, $templateMethods)],
            TemplateNodeKind::Loop, TemplateNodeKind::Counter => [
                "'<!--t:b-->'",
                $this->templateLoops->loop($templateNode, $scope, $templateMethods, $this),
                self::BLOCK_END,
            ],
            TemplateNodeKind::Import, TemplateNodeKind::Break, TemplateNodeKind::Continue => [],
        };
    }

    /**
     * @param TemplateNode $templateNode A `@component` node.
     *
     * @return list<string> Its markup between key markers: its compile-time key, and in a loop its own key escaped.
     */
    private function component(TemplateNode $templateNode): array
    {
        $key = $templateNode->binding === ''
            ? "'" . $templateNode->key . "'"
            : "'" . $templateNode->key . ":' . " . $templateNode->binding;

        return ['$renderPass->child(' . $templateNode->code . ', ' . $key . ')'];
    }

    /**
     * @param TemplateNode    $templateNode    A condition, or an `elseif` inside one.
     * @param list<string>    $scope           The variables in scope.
     * @param TemplateMethods $templateMethods The class's part methods.
     *
     * @return string A ternary between the parts of its two branches.
     */
    private function condition(TemplateNode $templateNode, array $scope, TemplateMethods $templateMethods): string
    {
        $then = $templateMethods->call($this->sequence($templateNode->children, $scope, $templateMethods));
        $otherwise = $templateNode->otherwise === []
            ? "''"
            : $templateMethods->call($this->sequence($templateNode->otherwise, $scope, $templateMethods));

        return '(' . $templateNode->code . "\n    ? " . $then . "\n    : " . $otherwise . ')';
    }

    /**
     * @param list<string> $pieces Expressions, in order.
     *
     * @return list<list<string>> Them in runs of at most twelve lines less one, the line a run's call to the next takes.
     */
    private function groups(array $pieces): array
    {
        $groups = [];
        $group = [];
        $lines = 0;

        foreach ($pieces as $piece) {
            $height = substr_count($piece, "\n") + 1;

            if ($group !== [] && $lines + $height > self::LINES - 1) {
                $groups[] = $group;
                $group = [];
                $lines = 0;
            }

            $group[] = $piece;
            $lines += $height;
        }

        return [...$groups, $group];
    }

    /**
     * @param string $text Markup.
     *
     * @return list<string> Its literals, one per line and at most eighty characters each.
     */
    private function texts(string $text): array
    {
        $literals = [];

        $lines = preg_split('/(?<=\\n)/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($lines === false ? [] : $lines as $line) {
            $literals = array_merge($literals, array_map($this->literal(...), mb_str_split($line, self::TEXT)));
        }

        return $literals;
    }

    /**
     * @param string $text Text with no newline but perhaps a last one.
     *
     * @return string Its PHP literal: single-quoted, or double-quoted for the newline.
     */
    private function literal(string $text): string
    {
        return str_ends_with($text, "\n")
            ? '"' . addcslashes(substr($text, 0, -1), '"\\$') . '\\n"'
            : "'" . addcslashes($text, "'\\") . "'";
    }
}
