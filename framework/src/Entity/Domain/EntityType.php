<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function array_merge;
use function preg_match;

/**
 * An entity type: a table, its key (a bigint the database generates) and its
 * fields. Names are identifiers, so the SQL built from them needs no quoting.
 */
final readonly class EntityType
{
    /**
     * @param string       $table  The table.
     * @param string       $key    The key column.
     * @param list<object> $fields Its Fields: objects, the list form elephc keeps (plan.md).
     *
     * @throws InvalidArgumentException When the table or the key is not an identifier.
     */
    public function __construct(
        public string $table,
        public string $key,
        private array $fields,
    ) {
        if (
            preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $table) !== 1
            || preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $key) !== 1
        ) {
            throw new InvalidArgumentException('Entity type "' . $table . '": the table and the key are identifiers.');
        }
    }

    /**
     * @return list<string> The key, then every field's column.
     */
    public function columns(): array
    {
        return array_merge([$this->key], $this->named(FieldType::Text), $this->named(FieldType::CreatedAt));
    }

    /**
     * @return list<string> The fields a caller writes: the Text fields.
     */
    public function writable(): array
    {
        return $this->named(FieldType::Text);
    }

    /**
     * @return list<string> The CreatedAt fields, which the insert sets to `now()`.
     */
    public function createdAt(): array
    {
        return $this->named(FieldType::CreatedAt);
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
