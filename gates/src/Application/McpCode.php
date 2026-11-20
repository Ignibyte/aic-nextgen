<?php

declare(strict_types=1);

namespace Gates\Application;

use function implode;
use function is_bool;
use function is_scalar;
use function is_string;
use function json_decode;
use function lcfirst;
use function preg_match;
use function str_contains;
use function str_repeat;
use function strlen;
use function strrpos;
use function substr;
use function var_export;

/**
 * Small pieces of the MCP code the build writes (#316): a literal from a JSON
 * value, a call that wraps onto one argument per line when it would pass 140
 * characters, a class's short name and the variable named for it.
 */
final readonly class McpCode
{
    private const int LINE_LIMIT = 140;

    /**
     * @param string $json A JSON scalar or null.
     *
     * @return string Its PHP literal; `null` for null or anything not a scalar.
     */
    public function literal(string $json): string
    {
        $value = json_decode($json);

        if (!is_scalar($value)) {
            return 'null';
        }

        return $this->value($value);
    }

    /**
     * @param bool|float|int|string|null $value A scalar or null.
     *
     * @return string Its PHP literal, `null`, `true` and `false` in lower case.
     */
    public function value(bool|float|int|string|null $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $this->string($value),
            default => var_export($value, true),
        };
    }

    /**
     * @param list<int|string> $values Strings or integers.
     *
     * @return string Their PHP list literal.
     */
    public function list(array $values): string
    {
        $literals = [];

        foreach ($values as $value) {
            $literals[] = $this->value($value);
        }

        return '[' . implode(', ', $literals) . ']';
    }

    /**
     * @param string       $callee What is called, with its opening (`new JsonSchema`).
     * @param list<string> $args   Its arguments, written.
     * @param int          $indent The spaces before the line it starts.
     * @param string       $prefix What comes before it on that line (`'amount' => `).
     * @param string       $suffix What follows it (`,` or `;`).
     *
     * @return string The call on one line, or one argument a line when that would pass the limit.
     */
    public function call(string $callee, array $args, int $indent, string $prefix = '', string $suffix = ''): string
    {
        $pad = str_repeat(' ', $indent);
        $line = $pad . $prefix . $callee . '(' . implode(', ', $args) . ')' . $suffix;

        if (strlen($line) <= self::LINE_LIMIT || $args === []) {
            return $line;
        }

        $lines = [$pad . $prefix . $callee . '('];

        foreach ($args as $arg) {
            $lines[] = $pad . '    ' . $arg . ',';
        }

        return implode("\n", [...$lines, $pad . ')' . $suffix]);
    }

    /**
     * @param string $class A class name.
     *
     * @return string Its short name.
     */
    public function short(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false
            ? $class
            : substr($class, $position + 1);
    }

    /**
     * @param string $class A class name.
     *
     * @return string The variable named for it (`ledgerSplitPart`), without the `$`.
     */
    public function variable(string $class): string
    {
        return lcfirst($this->short($class));
    }

    /**
     * @param string $value A string.
     *
     * @return string Its PHP literal: in double quotes when it holds a single quote and nothing double quotes read,
     *                as Rector writes it; in single quotes otherwise.
     */
    private function string(string $value): string
    {
        if (str_contains($value, "'") && preg_match('/["$\\\\]/', $value) !== 1) {
            return '"' . $value . '"';
        }

        return var_export($value, true);
    }
}
