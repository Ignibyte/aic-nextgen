<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

use function array_keys;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Values written to an entity type's fields (#184), checked against each
 * field: a string for Text, Date and Numeric, an int for Integer and Reference, a
 * bool for Boolean, and null only where the field is nullable. A bool is bound
 * as `true` or `false`, as Postgres reads it.
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
     * @param EntityType                          $entityType The entity type written.
     * @param array<string, string|int|bool|null> $values     Values by field name.
     *
     * @throws InvalidArgumentException When a name is not a writable field, or its value does not fit it.
     */
    public function __construct(
        EntityType $entityType,
        private array $values,
    ) {
        $columns = array_keys($values);
        $writable = $entityType->writable();

        foreach ($columns as $column) {
            if (!in_array($column, $writable, true)) {
                throw new InvalidArgumentException('The ' . $entityType->table . ' entity has no writable field "' . $column . '".');
            }

            self::check($entityType->table, $entityType->field($column), $values[$column]);
        }

        $this->columns = $columns;
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
        if ($value === null ? $field->nullable : self::fits($field->type, $value)) {
            return;
        }

        throw new InvalidArgumentException(
            'The ' . $table . ' entity\'s field "' . $field->name . '" does not take that value'
            . ($value === null ? ': it is not nullable.' : '.'),
        );
    }

    /**
     * @param FieldType       $fieldType The field's type.
     * @param string|int|bool $value     A value that is not null.
     *
     * @return bool Whether the value is of the PHP type the field type takes.
     */
    private static function fits(FieldType $fieldType, string|int|bool $value): bool
    {
        if ($fieldType === FieldType::Boolean) {
            return is_bool($value);
        }

        return $fieldType === FieldType::Integer || $fieldType === FieldType::Reference
            ? is_int($value)
            : is_string($value);
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
