<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function max;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_pad;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;

/**
 * The part methods of one generated template class, written as the compiler
 * makes them: each concatenates its pieces, and takes only the variables of
 * the scope its pieces use, so no parameter goes unused.
 */
final class TemplateMethods
{
    /**
     * A PHP string literal, single- or double-quoted.
     */
    private const string LITERAL = '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/';

    /**
     * Each method's source, by number.
     *
     * @var list<string>
     */
    private array $sources = [];

    /**
     * Each method's parameters, `Type $var`, by number.
     *
     * @var list<list<string>>
     */
    private array $parameters = [];

    /**
     * @param string $template  The template's file name, for the docblocks.
     * @param string $component The component's variable, without `$`.
     */
    public function __construct(
        private readonly string $template,
        private readonly string $component,
    ) {
    }

    /**
     * @param list<string> $scope  The variables in scope, `Type $var`, outermost first.
     * @param list<string> $pieces The expressions to concatenate, in order; at least one.
     *
     * @return int The new method's number.
     */
    public function add(array $scope, array $pieces): int
    {
        $parameters = $this->used($scope, implode(' ', $pieces));

        return $this->append($parameters, 'string', $this->body($pieces), 'Markup from ' . $this->template . '.');
    }

    /**
     * A part that runs a real loop (#97): it appends each pass's markup and returns it all, so `break` and `continue`
     * mean what PHP means.
     *
     * @param list<string> $scope  The variables in scope outside the loop, `Type $var`.
     * @param string       $header The loop's header: `foreach (...)` or `for (...)`.
     * @param list<string> $lines  The loop body's statements, each with its own nested lines.
     *
     * @return int The new method's number.
     */
    public function addLoop(array $scope, string $header, array $lines): int
    {
        $parameters = $this->used($scope, $header . ' ' . implode(' ', $lines));
        $body = (string) preg_replace('/^(?=.)/m', '            ', implode("\n", $lines));

        return $this->append($parameters, 'string', "        \$markup = '';\n\n        " . $header . " {\n" . $body
            . "\n        }\n\n        return \$markup;\n", 'Markup from ' . $this->template . '.');
    }

    /**
     * A part that computes a condition inside a loop (#97), so it reads the loop's typed variables.
     *
     * @param list<string> $scope The variables in scope, `Type $var`.
     * @param string       $code  The condition.
     *
     * @return int The new method's number.
     */
    public function addTest(array $scope, string $code): int
    {
        $parameters = $this->used($scope, $code);
        $summary = 'Whether a condition of ' . $this->template . ' holds.';

        return $this->append($parameters, 'bool', '        return ' . $code . ";\n", $summary);
    }

    /**
     * @param int $index A method's number.
     *
     * @return string The call to it, with the variables it takes.
     */
    public function call(int $index): string
    {
        $variables = array_map(static fn (string $parameter): string => '$' . self::variable($parameter), $this->parameters[$index]);

        return '$this->part' . $index . '(' . implode(', ', $variables) . ')';
    }

    /**
     * @return int How many methods there are.
     */
    public function count(): int
    {
        return count($this->sources);
    }

    /**
     * @return string Every method, a blank line between each.
     */
    public function source(): string
    {
        return implode("\n", $this->sources);
    }

    /**
     * @param list<string> $parameters The method's parameters, `Type $var`.
     * @param string       $return     Its return type.
     * @param string       $body       Its body, indented.
     * @param string       $returns    What it returns, for its docblock.
     *
     * @return int The new method's number.
     */
    private function append(array $parameters, string $return, string $body, string $returns): int
    {
        $index = count($this->sources);
        $this->parameters[] = $parameters;
        $this->sources[] = $this->docblock($parameters, $return . ' ' . $returns)
            . '    private function part' . $index . '(' . implode(', ', $parameters) . '): ' . $return . "\n    {\n"
            . $body . "    }\n";

        return $index;
    }

    /**
     * @param list<string> $scope The variables in scope, `Type $var`.
     * @param string       $code  Code that may read some of them.
     *
     * @return list<string> Those it reads, outside its string literals.
     */
    private function used(array $scope, string $code): array
    {
        preg_match_all('/\\$(\\w+)/', (string) preg_replace(self::LITERAL, '', $code), $matches);

        return array_values(array_filter(
            $scope,
            static fn (string $parameter): bool => in_array(self::variable($parameter), $matches[1], true),
        ));
    }

    /**
     * @param list<string> $parameters The method's parameters, `Type $var`.
     * @param string       $return     Its `@return`: the type and what it is.
     *
     * @return string Its docblock, the parameters aligned.
     */
    private function docblock(array $parameters, string $return): string
    {
        $types = array_map(static fn (string $parameter): string => substr($parameter, 0, (int) strrpos($parameter, ' ')), $parameters);
        $width = max([0, ...array_map(strlen(...), $types)]);
        $nameWidth = max([0, ...array_map(static fn (string $parameter): int => strlen(self::variable($parameter)) + 1, $parameters)]);
        $lines = '';

        foreach ($parameters as $at => $parameter) {
            $variable = self::variable($parameter);
            $lines .= '     * @param ' . str_pad($types[$at], $width) . ' ' . str_pad('$' . $variable, $nameWidth) . ' '
                . match ($variable) {
                    $this->component => 'The component.',
                    'renderPass' => 'The pass that writes its children.',
                    default => "A loop's variable.",
                } . "\n";
        }

        $throws = str_contains(implode(', ', $parameters), 'RenderPass $renderPass')
            ? "     * @throws UnknownComponent When a child has no registered template.\n     *\n"
            : '';

        return "    /**\n" . ($lines === '' ? '' : $lines . "     *\n") . $throws
            . '     * @return ' . $return . "\n     */\n";
    }

    /**
     * @param list<string> $pieces The expressions, in order.
     *
     * @return string `return` with them concatenated, one a line.
     */
    private function body(array $pieces): string
    {
        $lines = [];

        foreach ($pieces as $at => $piece) {
            $indent = $at === 0
                ? '        '
                : '            ';
            $lines[] = ($at === 0 ? '        return ' : '            . ') . str_replace("\n", "\n" . $indent, $piece);
        }

        return implode("\n", $lines) . ";\n";
    }

    /**
     * @param string $parameter `Type $var`.
     *
     * @return string `var`.
     */
    private static function variable(string $parameter): string
    {
        return substr($parameter, (int) strrpos($parameter, '$') + 1);
    }
}
