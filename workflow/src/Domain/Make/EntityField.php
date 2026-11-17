<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function implode;
use function lcfirst;
use function str_replace;
use function ucwords;

/**
 * One column `make:entity` writes (#123): its snake-case name and its type, `text`, `numeric`, `date`, `boolean`, or
 * since #307 `uuid`, `json`, `timestamp` or `choice` with its values, with what each type becomes in the entity, the
 * repository and the migration.
 */
final readonly class EntityField
{
    /**
     * @param string       $name    Its column, lower snake case.
     * @param string       $type    `text`, `numeric`, `date`, `boolean`, `uuid`, `json`, `timestamp` or `choice`.
     * @param list<string> $choices A choice's values; empty for every other type.
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $choices = [],
    ) {
    }

    /**
     * @return string Its property on the entity: `added_on` gives `addedOn`.
     */
    public function property(): string
    {
        return lcfirst(str_replace('_', '', ucwords($this->name, '_')));
    }

    /**
     * @return bool Whether it is a boolean, read and held as a bool; every other type is a string.
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * @return string Its `Field` in the repository's entity type, a choice with its values.
     */
    public function field(): string
    {
        $choices = $this->choices === []
            ? ''
            : ', choices: ' . $this->list();

        return "new Field('" . $this->name . "', FieldType::" . ucwords($this->type) . $choices . ')';
    }

    /**
     * @return string Its migration builder call.
     */
    public function column(): string
    {
        $named = "('" . $this->name . "'";

        return match ($this->type) {
            'numeric' => '->numeric' . $named . ', 12, 2)',
            'timestamp' => '->timestampTz' . $named . ')',
            'uuid' => '->column' . $named . ', $schema->types()->uuid())',
            'json' => '->column' . $named . ', $schema->types()->jsonb())',
            'choice' => '->column' . $named . ', $schema->types()->choice(' . $this->list() . '))',
            default => '->' . $this->type . $named . ')',
        };
    }

    /**
     * @return string The choice's values as a PHP list.
     */
    private function list(): string
    {
        return "['" . implode("', '", $this->choices) . "']";
    }
}
