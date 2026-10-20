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
        $code = (string) preg_replace(self::LITERAL, '', implode(' ', $pieces));
        preg_match_all('/\\$(\\w+)/', $code, $matches);
        $parameters = array_values(array_filter(
            $scope,
            static fn (string $parameter): bool => in_array(self::variable($parameter), $matches[1], true),
        ));
        $index = count($this->sources);
        $this->parameters[] = $parameters;
        $this->sources[] = $this->docblock($parameters)
            . '    private function part' . $index . '(' . implode(', ', $parameters) . "): string\n    {\n"
            . $this->body($pieces) . "    }\n";

        return $index;
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
     *
     * @return string Its docblock, the parameters aligned.
     */
    private function docblock(array $parameters): string
    {
        $types = array_map(static fn (string $parameter): string => substr($parameter, 0, (int) strrpos($parameter, ' ')), $parameters);
        $width = max([0, ...array_map(strlen(...), $types)]);
        $nameWidth = max([0, ...array_map(static fn (string $parameter): int => strlen(self::variable($parameter)) + 1, $parameters)]);
        $lines = '';

        foreach ($parameters as $at => $parameter) {
            $variable = self::variable($parameter);
            $lines .= '     * @param ' . str_pad($types[$at], $width) . ' ' . str_pad('$' . $variable, $nameWidth) . ' '
                . ($variable === $this->component ? 'The component.' : "A loop's item.") . "\n";
        }

        return "    /**\n" . ($lines === '' ? '' : $lines . "     *\n")
            . '     * @return string Markup from ' . $this->template . ".\n     */\n";
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
