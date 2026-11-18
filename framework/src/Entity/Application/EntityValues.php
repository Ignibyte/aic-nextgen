<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldFormats;
use InvalidArgumentException;

use function array_keys;
use function in_array;
use function is_bool;

/**
 * Values written to an entity type's fields (#184), checked against each
 * field by `FieldFormats` (#307): a bool for Boolean, an int for Integer and an
 * integer-keyed Reference, and for every other type a string in its form; null
 * only where the field is nullable. A bool is bound as `true` or `false`, as
 * Postgres reads it, and Postgres casts each string to its column's type.
 */
final readonly class EntityValues
{
    /**
     * The checked columns, in the order given.
     *
     * @var list<string>
     */
    private array $columns;

    /**
     * The values by column: the caller's, then those the store sets (#308).
     *
     * @var array<string, string|int|bool|null>
     */
    private array $values;

    /**
     * @param EntityType                          $entityType The entity type written.
     * @param array<string, string|int|bool|null> $values     Values by field name.
     * @param array<string, string>               $set        Values the store sets itself, past the writable check: an
     *                                                        insert's initial state (#308), from `initialState()`.
     *
     * @throws InvalidArgumentException When the entity type is read-only, a name is not a writable field, or its value
     *                                  does not fit it.
     */
    public function __construct(
        EntityType $entityType,
        array $values,
        array $set = [],
    ) {
        if ($entityType->readOnly) {
            throw new InvalidArgumentException('The ' . $entityType->table . ' entity is a view: it is read, never written.');
        }

        $columns = array_keys($values);
        $writable = $entityType->writable();

        foreach ($columns as $column) {
            if (!in_array($column, $writable, true)) {
                throw new InvalidArgumentException('The ' . $entityType->table . ' entity has no writable field "' . $column . '".');
            }

            self::check($entityType->table, $entityType->field($column), $values[$column]);
        }

        foreach ($set as $column => $value) {
            self::check($entityType->table, $entityType->field($column), $value);
            $values[$column] = $value;
            $columns[] = $column;
        }

        $this->columns = $columns;
        $this->values = $values;
    }

    /**
     * @return list<string> The written columns, in order.
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string|int|null> Their values, bound, in the same order.
     */
    public function params(): array
    {
        $params = [];

        foreach ($this->columns as $column) {
            $params[] = self::bound($this->values[$column] ?? null);
        }

        return $params;
    }

    /**
     * @param string               $table The entity type's table, for the message.
     * @param Field                $field The field written.
     * @param string|int|bool|null $value Its value.
     *
     * @throws InvalidArgumentException When the value does not fit the field.
     */
    private static function check(string $table, Field $field, string|int|bool|null $value): void
    {
        if ($value === null ? $field->nullable : (new FieldFormats())->fits($field, $value)) {
            return;
        }

        throw new InvalidArgumentException(
            'The ' . $table . ' entity\'s field "' . $field->name . '" does not take that value'
            . ($value === null ? ': it is not nullable.' : '.'),
        );
    }

    /**
     * @param string|int|bool|null $value A checked value.
     *
     * @return string|int|null It as bound: a bool as `true` or `false`.
     */
    private static function bound(string|int|bool|null $value): string|int|null
    {
        if (!is_bool($value)) {
            return $value;
        }

        return $value
            ? 'true'
            : 'false';
    }
}
