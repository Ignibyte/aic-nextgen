<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use stdClass;

use function array_key_exists;
use function array_keys;
use function array_map;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Typed reads over a decoded JSON object (#100), each value narrowed where it
 * is read, so nothing the workflow keeps is untyped. A missing key, a wrong
 * type or an unknown key is refused by name.
 */
final readonly class JsonReader
{
    /**
     * @param stdClass $object The object.
     * @param string   $what   What it is, for refusals: `var/workflow/run.json`, `its ticket`.
     */
    public function __construct(
        private stdClass $object,
        private string $what,
    ) {
    }

    /**
     * @param list<string> $known The keys it may hold.
     *
     * @throws WorkflowRefusal When it holds another.
     */
    public function only(array $known): void
    {
        foreach (array_keys(get_object_vars($this->object)) as $key) {
            if (!in_array($key, $known, true)) {
                throw new WorkflowRefusal($this->what . ' has an unknown key "' . $key . '".');
            }
        }
    }

    /**
     * @param string $key A key.
     *
     * @throws WorkflowRefusal When it is missing or not a string.
     *
     * @return string Its value.
     */
    public function string(string $key): string
    {
        $value = get_object_vars($this->object)[$key] ?? null;

        if (!is_string($value)) {
            throw $this->wrong($key, 'a string');
        }

        return $value;
    }

    /**
     * @param string $key A key.
     *
     * @throws WorkflowRefusal When it is missing, or neither a string nor null.
     *
     * @return string|null Its value.
     */
    public function nullableString(string $key): ?string
    {
        $values = get_object_vars($this->object);

        if (!array_key_exists($key, $values) || $values[$key] !== null && !is_string($values[$key])) {
            throw $this->wrong($key, 'a string or null');
        }

        return $values[$key];
    }

    /**
     * @param string $key A key.
     *
     * @throws WorkflowRefusal When it is missing or not an integer.
     *
     * @return int Its value.
     */
    public function int(string $key): int
    {
        $value = get_object_vars($this->object)[$key] ?? null;

        if (!is_int($value)) {
            throw $this->wrong($key, 'an integer');
        }

        return $value;
    }

    /**
     * @param string $key A key.
     *
     * @throws WorkflowRefusal When it is missing or not an object.
     *
     * @return self A reader over it.
     */
    public function object(string $key): self
    {
        $value = get_object_vars($this->object)[$key] ?? null;

        if (!$value instanceof stdClass) {
            throw $this->wrong($key, 'an object');
        }

        return new self($value, $this->what . '\'s "' . $key . '"');
    }

    /**
     * @param string $key A key.
     *
     * @throws WorkflowRefusal When it is missing, or not a list of objects.
     *
     * @return list<self> A reader over each object, in order.
     */
    public function objects(string $key): array
    {
        $value = get_object_vars($this->object)[$key] ?? null;

        if (!is_array($value)) {
            throw $this->wrong($key, 'a list');
        }

        $readers = [];

        foreach ($value as $item) {
            if (!$item instanceof stdClass) {
                throw $this->wrong($key, 'a list of objects');
            }

            $readers[] = new self($item, $this->what . '\'s "' . $key . '"');
        }

        return $readers;
    }

    /**
     * @return list<string> Its keys, in order.
     */
    public function keys(): array
    {
        return array_map(static fn (int|string $key): string => (string) $key, array_keys(get_object_vars($this->object)));
    }

    /**
     * @param string $key  The key.
     * @param string $type What it should be.
     *
     * @return WorkflowRefusal The refusal naming it.
     */
    private function wrong(string $key, string $type): WorkflowRefusal
    {
        return new WorkflowRefusal($this->what . '\'s "' . $key . '" is missing or not ' . $type . '.');
    }
}
