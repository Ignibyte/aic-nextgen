<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use function in_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_validate;
use function preg_match;

/**
 * Whether a value fits a field (#307): a bool for a Boolean, an int for an
 * Integer and for a key or Reference of an integer-keyed type, and for every
 * other type a string in its form. A uuid is 8-4-4-4-12 hex digits, a date
 * `YYYY-MM-DD`, a timestamp ISO 8601 with its zone, a decimal digits with an
 * optional sign and point, a JSON document valid JSON, and a choice one of the
 * field's values.
 */
final readonly class FieldFormats
{
    /** A UUID, any version, either case. */
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** A calendar date. */
    private const string DATE = '/^\\d{4}-\\d{2}-\\d{2}$/';

    /** A point in time with its zone: `2026-11-17T09:30:00Z`, `2026-11-17 09:30:00.25+01:00`. */
    private const string TIMESTAMP = '/^\\d{4}-\\d{2}-\\d{2}[T ]\\d{2}:\\d{2}(:\\d{2}(\\.\\d{1,6})?)?(Z|[+-]\\d{2}(:?\\d{2})?)$/';

    /** An exact decimal. */
    private const string DECIMAL = '/^-?\\d+(\\.\\d+)?$/';

    /**
     * @param Field           $field A field.
     * @param string|int|bool $value A value for it, not null.
     *
     * @return bool Whether the value fits the field's type.
     */
    public function fits(Field $field, string|int|bool $value): bool
    {
        if ($field->type === FieldType::Boolean) {
            return is_bool($value);
        }

        if ($field->type === FieldType::Integer) {
            return is_int($value);
        }

        return $field->references instanceof EntityType
            ? $this->key($field->references->keyType, $value)
            : is_string($value) && $this->formatted($field, $value);
    }

    /**
     * @param FieldType       $fieldType An entity type's key type.
     * @param string|int|bool $value     A key.
     *
     * @return bool Whether it is one: an int, or a uuid for a uuid-keyed type.
     */
    public function key(FieldType $fieldType, string|int|bool $value): bool
    {
        return $fieldType === FieldType::Uuid
            ? is_string($value) && preg_match(self::UUID, $value) === 1
            : is_int($value);
    }

    /**
     * @param Field  $field A field of a string type.
     * @param string $value A value for it.
     *
     * @return bool Whether it is in the type's form.
     */
    private function formatted(Field $field, string $value): bool
    {
        return match ($field->type) {
            FieldType::Uuid => preg_match(self::UUID, $value) === 1,
            FieldType::Json => json_validate($value),
            FieldType::Choice => in_array($value, $field->choices, true),
            FieldType::Timestamp => preg_match(self::TIMESTAMP, $value) === 1,
            FieldType::Date => preg_match(self::DATE, $value) === 1,
            FieldType::Numeric => preg_match(self::DECIMAL, $value) === 1,
            FieldType::Text, FieldType::CreatedAt, FieldType::UpdatedAt, FieldType::Boolean, FieldType::Integer,
            FieldType::Reference => true,
        };
    }
}
