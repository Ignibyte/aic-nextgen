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
 * repository and the migration, and since #324 in a list page's row; since #338, read from a migration, an `integer`,
 * a `reference` to another table's integer key, and a nullable column.
 */
final readonly class EntityField
{
    /**
     * @param string       $name       Its column, lower snake case.
     * @param string       $type       `text`, `numeric`, `date`, `boolean`, `uuid`, `json`, `timestamp` or `choice`, and
     *                                 `integer` in a seed (#320) and an existing table's entity, with `reference` (#338).
     * @param list<string> $choices    A choice's values; empty for every other type.
     * @param bool         $nullable   Whether it may be null (#338).
     * @param string       $references The table a `reference` holds the key of (#338); empty for every other type.
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $choices = [],
        public bool $nullable = false,
        public string $references = '',
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
     * @return string Its PHP type in a list page's row (#324) or an entity (#338): `int`, `bool` or `string`.
     */
    public function phpType(): string
    {
        return match ($this->type) {
            'integer', 'reference' => 'int',
            'boolean' => 'bool',
            default => 'string',
        };
    }

    /**
     * @return string How a repository reads it from a row (#324), such as `$row->int('capacity')`; a nullable one with
     *                `intOrNull()` or `stringOrNull()` (#338).
     */
    public function read(): string
    {
        $nullable = $this->nullable
            ? 'OrNull'
            : '';

        return '$row->' . $this->phpType() . $nullable . "('" . $this->name . "')";
    }

    /**
     * @return string Its name in words, such as `due on`.
     */
    public function words(): string
    {
        return str_replace('_', ' ', $this->name);
    }

    /**
     * @return bool Whether it is a boolean, read and held as a bool; every other type is a string.
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * @return string Its `Field` in the repository's entity type, a choice with its values, a nullable one and a
     *                reference with what they say (#338).
     */
    public function field(): string
    {
        $choices = $this->choices === []
            ? ''
            : ', choices: ' . $this->list();
        $nullable = $this->nullable
            ? ', nullable: true'
            : '';
        $references = $this->references === ''
            ? ''
            : ", references: new EntityType('" . $this->references . "', 'id', [])";

        return "new Field('" . $this->name . "', FieldType::" . ucwords($this->type) . $nullable . $references . $choices . ')';
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
     * @return string A PHP literal of its type, for a seed's first row (#320): `title` gives `'title one'`.
     */
    public function example(): string
    {
        return match ($this->type) {
            'boolean' => 'true',
            'choice' => "'" . ($this->choices[0] ?? '') . "'",
            'date' => "'2026-01-01'",
            'integer' => '1',
            'json' => "'{}'",
            'numeric' => "'1.00'",
            'timestamp' => "'2026-01-01 00:00:00+00'",
            'uuid' => "'00000000-0000-4000-8000-000000000001'",
            default => "'" . str_replace('_', ' ', $this->name) . " one'",
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
