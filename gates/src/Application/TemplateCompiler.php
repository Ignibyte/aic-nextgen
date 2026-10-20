<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateMethods;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function addcslashes;
use function array_map;
use function array_merge;
use function array_reverse;
use function lcfirst;
use function mb_str_split;
use function preg_split;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strrpos;
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
     * The literals that close a component's key markers, a condition's and a loop's morph markers.
     */
    private const string COMPONENT_END = "'<!--/aic:c-->'";

    private const string IF_END = "'<!--/aic:if-->'";

    private const string EACH_END = "'<!--/aic:each-->'";

    /**
     * The characters of text one literal holds.
     */
    private const int TEXT = 80;

    /**
     * @param TemplateImports $templateImports Writes the generated file's opening and imports.
     */
    public function __construct(
        private TemplateImports $templateImports = new TemplateImports(),
    ) {
    }

    /**
     * @param list<TemplateNode> $nodes     The template's nodes.
     * @param string             $namespace The component's namespace.
     * @param string             $component The component's class name.
     *
     * @throws InvalidArgumentException When the template is too big for one class.
     *
     * @return string The source of `<component>Template`, in the same namespace.
     */
    public function compile(array $nodes, string $namespace, string $component): string
    {
        $variable = lcfirst($component);
        $templateMethods = new TemplateMethods($component . '.tpl', $variable);
        $first = $this->sequence($nodes, [$component . ' $' . $variable], $templateMethods);
        $parts = $templateMethods->source();
        $parameter = $component . ' $' . $variable;
        $class = $this->templateImports->head($nodes, $namespace, $parts)
            . "/**\n * " . $component . "'s markup, compiled from " . $component
            . ".tpl by bin/aic-build (#36):\n * edit the template and run composer run build, never this file.\n */\n"
            . 'final readonly class ' . $component . "Template\n{\n"
            . (str_contains($parts, 'self::text(') ? "    use RendersMarkup;\n\n" : '')
            . "    /**\n     * @param " . $parameter . " The component.\n     *\n     * @return Html Its markup.\n     */\n"
            . '    public function render(' . $parameter . "): Html\n    {\n"
            . '        return new Html(' . $templateMethods->call($first) . ");\n    }\n\n" . $parts . "}\n";

        if ($templateMethods->count() > self::PARTS || substr_count($class, "\n") > self::CLASS_LINES) {
            throw new InvalidArgumentException($component . '.tpl is too big for one class (' . $templateMethods->count()
                . ' part methods, ' . substr_count($class, "\n") . ' lines): move some of it into components.');
        }

        return $class;
    }

    /**
     * @param list<TemplateNode> $nodes           A run of nodes.
     * @param list<string>       $scope           The variables in scope, `Type $var`, outermost first.
     * @param TemplateMethods    $templateMethods The class's part methods.
     *
     * @return int The number of the part method that renders the run.
     */
    private function sequence(array $nodes, array $scope, TemplateMethods $templateMethods): int
    {
        $pieces = [];

        foreach ($nodes as $node) {
            $pieces = array_merge($pieces, $this->pieces($node, $scope, $templateMethods));
        }

        return $this->chain($pieces === [] ? ["''"] : $pieces, $scope, $templateMethods);
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
            TemplateNodeKind::Raw => [$this->wrapped($templateNode->code) . '->markup()'],
            TemplateNodeKind::Component => $this->component($templateNode),
            TemplateNodeKind::Condition => ["'<!--aic:if-->'", $this->condition($templateNode, $scope, $templateMethods), self::IF_END],
            TemplateNodeKind::Loop => ["'<!--aic:each-->'", $this->loop($templateNode, $scope, $templateMethods), self::EACH_END],
            TemplateNodeKind::Import => [],
        };
    }

    /**
     * @param TemplateNode $templateNode A `@component` node.
     *
     * @return list<string> Its markup between key markers: its compile-time key, and in a loop its own key escaped.
     */
    private function component(TemplateNode $templateNode): array
    {
        $markup = $this->wrapped($templateNode->code) . '->render()->markup()';

        $open = $templateNode->binding === ''
            ? ["'<!--aic:c " . $templateNode->key . "-->'"]
            : ["'<!--aic:c " . $templateNode->key . "/'", 'self::text(' . $templateNode->binding . ')', "'-->'"];

        return [...$open, $markup, self::COMPONENT_END];
    }

    /**
     * @param TemplateNode    $templateNode    An `@if` node.
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
     * @param TemplateNode    $templateNode    A `@foreach` node.
     * @param list<string>    $scope           The variables in scope.
     * @param TemplateMethods $templateMethods The class's part methods.
     *
     * @return string Its items mapped through the part of its body, joined; the part itself
     *                when it takes only the item, an arrow function otherwise.
     */
    private function loop(TemplateNode $templateNode, array $scope, TemplateMethods $templateMethods): string
    {
        $index = $this->sequence($templateNode->children, [...$scope, $templateNode->binding], $templateMethods);
        $call = $templateMethods->call($index);
        $item = substr($templateNode->binding, (int) strrpos($templateNode->binding, '$'));
        $mapper = $call === '$this->part' . $index . '(' . $item . ')'
            ? '$this->part' . $index . '(...)'
            : 'fn (' . $templateNode->binding . '): string => ' . $call;

        return "implode('', array_map(\n    " . $mapper . ",\n    " . $templateNode->code . ",\n))";
    }

    /**
     * @param list<string> $pieces Expressions, in order.
     *
     * @return list<list<string>> Them in runs of at most twelve lines less one, the line a run's call to the next takes.
     */
    private function groups(array $pieces): array
    {
        $groups = [[]];
        $index = 0;
        $lines = 0;

        foreach ($pieces as $piece) {
            $height = substr_count($piece, "\n") + 1;

            if ($groups[$index] !== [] && $lines + $height > self::LINES - 1) {
                $index += 1;
                $groups[$index] = [];
                $lines = 0;
            }

            $groups[$index][] = $piece;
            $lines += $height;
        }

        return $groups;
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

    /**
     * @param string $code An object expression.
     *
     * @return string It, in parentheses when it is a `new`, so a call can follow.
     */
    private function wrapped(string $code): string
    {
        return str_starts_with($code, 'new ')
            ? '(' . $code . ')'
            : $code;
    }
}
