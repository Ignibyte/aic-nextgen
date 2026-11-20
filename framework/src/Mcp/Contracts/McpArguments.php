<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * A call's arguments (#316): the `arguments` object of a `tools/call` or a
 * `prompts/get`, kept as its JSON text and read one field at a time, each read
 * checking the field's type. A tool's generated adapter reads them into the
 * tool's input; a prompt reads its strings. Every refusal is an
 * `McpArgumentException` naming the field.
 *
 * @api
 */
final readonly class McpArguments
{
    /**
     * @param string $json The arguments' JSON object; an empty object for none.
     */
    public function __construct(
        private string $json = '{}',
    ) {
    }

    /**
     * @param string $defaults A JSON object of the defaults, as the build writes them from the input's constructor.
     *
     * @return McpArguments These arguments, with each default the client left out.
     */
    public function withDefaults(string $defaults): self
    {
        $given = json_decode($this->json, true);
        $filled = json_decode($defaults, true);

        if (!is_array($given) || !is_array($filled)) {
            return $this;
        }

        $merged = $given + $filled;

        return new self($merged === [] ? '{}' : (string) json_encode($merged));
    }

    /**
     * @param string $name A field's name.
     *
     * @return bool Whether the arguments hold it, null or not.
     */
    public function has(string $name): bool
    {
        return in_array($name, $this->names(), true);
    }

    /**
     * @param string $name A field's name.
     *
     * @return bool Whether it is present and null.
     */
    public function isNull(string $name): bool
    {
        return $this->has($name) && $this->field($name, 'a value') === 'null';
    }

    /**
     * @return list<string> The names of the fields the arguments hold, in order.
     */
    public function names(): array
    {
        $decoded = json_decode($this->json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $names = [];

        foreach (array_keys($decoded) as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    /**
     * @param list<string> $names The fields the call takes.
     *
     * @throws McpArgumentException When the arguments hold a field outside them.
     */
    public function only(array $names): void
    {
        $unknown = array_values(array_diff($this->names(), $names));

        if ($unknown !== []) {
            throw new McpArgumentException(sprintf(
                'The argument "%s" is not one this takes; it takes %s.',
                $unknown[0],
                $names === [] ? 'none' : implode(', ', $names),
            ));
        }
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not a string.
     *
     * @return string Its value.
     */
    public function string(string $name): string
    {
        $value = json_decode($this->field($name, 'a string'));

        if (!is_string($value)) {
            throw $this->wrong($name, 'a string');
        }

        return $value;
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not an integer.
     *
     * @return int Its value.
     */
    public function int(string $name): int
    {
        $value = json_decode($this->field($name, 'an integer'));

        if (!is_int($value)) {
            throw $this->wrong($name, 'an integer');
        }

        return $value;
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not a number.
     *
     * @return float Its value; an integer reads as its float.
     */
    public function float(string $name): float
    {
        $value = json_decode($this->field($name, 'a number'));

        if (!is_int($value) && !is_float($value)) {
            throw $this->wrong($name, 'a number');
        }

        return (float) $value;
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not true or false.
     *
     * @return bool Its value.
     */
    public function bool(string $name): bool
    {
        $value = json_decode($this->field($name, 'true or false'));

        if (!is_bool($value)) {
            throw $this->wrong($name, 'true or false');
        }

        return $value;
    }

    /**
     * @param string       $name    A field's name.
     * @param list<string> $choices What it may be.
     *
     * @throws McpArgumentException When it is missing or not one of them.
     *
     * @return string Its value.
     */
    public function choice(string $name, array $choices): string
    {
        $value = $this->string($name);

        if (!in_array($value, $choices, true)) {
            throw $this->wrong($name, 'one of ' . implode(', ', $choices));
        }

        return $value;
    }

    /**
     * @param string    $name    A field's name.
     * @param list<int> $choices What it may be.
     *
     * @throws McpArgumentException When it is missing or not one of them.
     *
     * @return int Its value.
     */
    public function intChoice(string $name, array $choices): int
    {
        $value = $this->int($name);

        if (!in_array($value, $choices, true)) {
            throw $this->wrong($name, 'one of ' . implode(', ', $choices));
        }

        return $value;
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not a list of strings.
     *
     * @return list<string> Its members, in order.
     */
    public function strings(string $name): array
    {
        $members = [];

        foreach ($this->members($name, 'a list of strings') as $text) {
            $member = json_decode($text);

            if (!is_string($member)) {
                throw $this->wrong($name, 'a list of strings');
            }

            $members[] = $member;
        }

        return $members;
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing or not a list of integers.
     *
     * @return list<int> Its members, in order.
     */
    public function ints(string $name): array
    {
        $members = [];

        foreach ($this->members($name, 'a list of integers') as $text) {
            $member = json_decode($text);

            if (!is_int($member)) {
                throw $this->wrong($name, 'a list of integers');
            }

            $members[] = $member;
        }

        return $members;
    }

    /**
     * @param string $name A field's name.
     * @param string $what What it must be, for the refusal.
     *
     * @throws McpArgumentException When it is missing or not a list.
     *
     * @return list<string> Each member's JSON text, in order.
     */
    private function members(string $name, string $what): array
    {
        $value = json_decode($this->field($name, $what), true);

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->wrong($name, $what);
        }

        $members = [];

        foreach (array_keys($value) as $index) {
            $members[] = (string) json_encode($value[$index]);
        }

        return $members;
    }

    /**
     * @param string $name A field's name.
     * @param string $what What it must be, for the refusal.
     *
     * @throws McpArgumentException When the arguments do not hold it.
     *
     * @return string Its value's JSON text.
     */
    private function field(string $name, string $what): string
    {
        $decoded = json_decode($this->json, true);

        if (!is_array($decoded) || !array_key_exists($name, $decoded)) {
            throw new McpArgumentException(sprintf('The argument "%s" is missing; it takes %s.', $name, $what));
        }

        return (string) json_encode($decoded[$name]);
    }

    /**
     * @param string $name A field's name.
     * @param string $what What it must be.
     *
     * @return McpArgumentException The refusal, naming both.
     */
    private function wrong(string $name, string $what): McpArgumentException
    {
        return new McpArgumentException(sprintf('The argument "%s" must be %s.', $name, $what));
    }
}
