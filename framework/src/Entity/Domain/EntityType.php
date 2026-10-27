<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * An entity type: a table, its key (a bigint the database generates) and its
 * fields. Names are identifiers, so the SQL built from them needs no quoting.
 */
final readonly class EntityType
{
    /**
     * @param string       $table    The table, or a view.
     * @param string       $key      The key column.
     * @param list<object> $fields   Its Fields: objects, the list form elephc keeps (plan.md).
     * @param bool         $readOnly Whether it maps a view (#191): queried like a table, never written.
     *
     * @throws InvalidArgumentException When the table or the key is not an identifier.
     */
    public function __construct(
        public string $table,
        public string $key,
        private array $fields,
        public bool $readOnly = false,
    ) {
        if (
            preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $table) !== 1
            || preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $key) !== 1
        ) {
            throw new InvalidArgumentException('Entity type "' . $table . '": the table and the key are identifiers.');
        }
    }

    /**
     * @return list<string> The key, then every field's column, in order.
     */
    public function columns(): array
    {
        $columns = [$this->key];

        foreach ($this->fields as $field) {
            if (!($field instanceof Field)) {
                continue;
            }

            $columns[] = $field->name;
        }

        return $columns;
    }

    /**
     * @return list<string> The fields a caller writes: all but the CreatedAt fields, in order.
     */
    public function writable(): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            if (!($field instanceof Field) || !$field->type->isWritable()) {
                continue;
            }

            $names[] = $field->name;
        }

        return $names;
    }

    /**
     * @return list<string> The CreatedAt fields, which the insert sets to `now()`.
     */
    public function createdAt(): array
    {
        return $this->named(FieldType::CreatedAt);
    }

    /**
     * @return list<string> The UpdatedAt fields, which the insert and every update set to `now()` (#184).
     */
    public function updatedAt(): array
    {
        return $this->named(FieldType::UpdatedAt);
    }

    /**
     * @param string $name A field's column.
     *
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return Field The field.
     */
    public function field(string $name): Field
    {
        foreach ($this->fields as $field) {
            if ($field instanceof Field && $field->name === $name) {
                return $field;
            }
        }

        throw new InvalidArgumentException('The ' . $this->table . ' entity has no field "' . $name . '".');
    }

    /**
     * @param FieldType $fieldType A field type.
     *
     * @return list<string> The columns of the fields of that type, in order.
     */
    private function named(FieldType $fieldType): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            if (!$field instanceof Field) {
                continue;
            }

            if ($field->type !== $fieldType) {
                continue;
            }

            $names[] = $field->name;
        }

        return $names;
    }
}
