<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

use function ctype_digit;

/**
 * One row a detail page shows (#341), found by its key through a read-only
 * entity type of the fields the page names. A choice is read as its text;
 * a key of the wrong shape finds no row.
 */
final readonly class DetailRows
{
    /**
     * @param EntityStore $entityStore The rows.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @param string                   $table     The table.
     * @param string                   $key       Its key column.
     * @param FieldType                $fieldType The key's type, `Integer` or `Uuid`.
     * @param array<string, FieldType> $fields    The fields the page shows, by name; the key among them is read as the key.
     * @param string                   $value     The key the path names.
     *
     * @throws QueryException           When the row cannot be read.
     * @throws InvalidArgumentException When the table, the key or a field is not an identifier.
     *
     * @return Row|null The row; null when there is none.
     */
    public function find(string $table, string $key, FieldType $fieldType, array $fields, string $value): ?Row
    {
        if ($fieldType === FieldType::Integer && !ctype_digit($value)) {
            return null;
        }

        $entityType = new EntityType($table, $key, $this->declared($key, $fields), true, $fieldType);

        return $this->entityStore->query($entityType)
            ->where($key, $fieldType === FieldType::Integer ? (int) $value : $value)
            ->first();
    }

    /**
     * @param string                   $key    The key column.
     * @param array<string, FieldType> $fields The fields, by name.
     *
     * @throws InvalidArgumentException When a field is not an identifier.
     *
     * @return list<Field> Every field but the key, a choice as text.
     */
    private function declared(string $key, array $fields): array
    {
        $declared = [];

        foreach ($fields as $name => $type) {
            if ($name === $key) {
                continue;
            }

            $declared[] = new Field($name, $type === FieldType::Choice ? FieldType::Text : $type);
        }

        return $declared;
    }
}
