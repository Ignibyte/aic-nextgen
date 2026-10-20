<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;
use Gates\Domain\TemplateMethods;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function addcslashes;
use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_reverse;
use function array_unique;
use function array_values;
use function implode;
use function lcfirst;
use function mb_str_split;
use function preg_split;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strrpos;
use function substr;
use function substr_count;

use const PREG_SPLIT_NO_EMPTY;
use const SORT_FLAG_CASE;
use const SORT_STRING;

/**
 * Compiles a component template's nodes (#36) into a final readonly class
 * whose `render()` gives the component's Html. The output passes every gate
 * as it is written: each run of output is a part method of at most twelve
 * lines, chained to the next; a condition is a ternary between two parts; a
 * loop maps its typed items through a part. A template that needs more part
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
     * The imports a part method's call needs, by the call.
     */
    private const array NEEDS = [
        'array_map(' => ['function array_map', 'function implode'],
        'self::text(' => [RendersMarkup::class],
    ];

    /**
     * The characters of text one literal holds.
     */
    private const int TEXT = 80;

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
        $class = $this->head($nodes, $namespace, $parts) . "/**\n * " . $component . "'s markup, compiled from " . $component
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
     * @param list<TemplateNode> $nodes     The template's nodes.
     * @param string             $namespace The class's namespace.
     * @param string             $parts     The part methods' source.
     *
     * @return string The file's opening, through its imports.
     */
    private function head(array $nodes, string $namespace, string $parts): string
    {
        $imports = [Html::class, ...$this->imported($nodes)];

        foreach (self::NEEDS as $call => $names) {
            $imports = str_contains($parts, $call)
                ? [...$imports, ...$names]
                : $imports;
        }

        $functions = array_filter($imports, static fn (string $import): bool => str_starts_with($import, 'function '));
        $classes = array_diff($imports, $functions);

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . $namespace . ";\n\n" . $this->uses($classes) . $this->uses($functions);
    }

    /**
     * @param list<TemplateNode> $nodes The template's nodes.
     *
     * @return list<string> The names its `@use` lines import.
     */
    private function imported(array $nodes): array
    {
        $imports = [];

        foreach ($nodes as $node) {
            if ($node->kind !== TemplateNodeKind::Import) {
                continue;
            }

            $imports[] = $node->code;
        }

        return $imports;
    }

    /**
     * @param array<int, string> $names Imported names.
     *
     * @return string Their `use` lines, sorted and unique, and a blank line; nothing for none.
     */
    private function uses(array $names): string
    {
        $unique = array_values(array_unique($names));

        if ($unique === []) {
            return '';
        }

        sort($unique, SORT_STRING | SORT_FLAG_CASE);

        return implode('', array_map(static fn (string $name): string => 'use ' . $name . ";\n", $unique)) . "\n";
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
            TemplateNodeKind::Component => [$this->wrapped($templateNode->code) . '->render()->markup()'],
            TemplateNodeKind::Condition => [$this->condition($templateNode, $scope, $templateMethods)],
            TemplateNodeKind::Loop => [$this->loop($templateNode, $scope, $templateMethods)],
            TemplateNodeKind::Import => [],
        };
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
