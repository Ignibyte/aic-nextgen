<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * The closed set of field types an entity's fields come from. A new type joins
 * with its first caller.
 */
enum FieldType
{
    /** Text, written and read as a string. */
    case Text;

    /** When the row was made: the database's `now()` at insert, never written by hand. */
    case CreatedAt;

    /** A calendar date, written and read as a `YYYY-MM-DD` string; any other form is refused (#307). */
    case Date;

    /** An exact decimal, written and read as its decimal string (`-12.50`): money never passes through a float. */
    case Numeric;

    /** True or false, written and read as a bool. */
    case Boolean;

    /** A whole number, written and read as an int (#184). */
    case Integer;

    /** When the row last changed: `now()` at insert and at every update, never written by hand (#184). */
    case UpdatedAt;

    /**
     * Another entity type's key, written and read as an int, or as a uuid string when that type is keyed by uuid
     * (#307); a query can join through it (#190).
     */
    case Reference;

    /** A UUID, written and read as its lower-case string (#307); also an entity type's key type. */
    case Uuid;

    /** A JSON document, written and read as its text, stored as `jsonb` (#307): a value class encodes itself. */
    case Json;

    /** One of the field's closed list of values, written and read as a string (#307): a backed enum's `->value`. */
    case Choice;

    /** A point in time with its zone, written as ISO 8601 text and read as text or through `Row::dateTime()` (#307). */
    case Timestamp;

    /**
     * @return bool Whether a caller writes it: every type but CreatedAt and UpdatedAt.
     */
    public function isWritable(): bool
    {
        return $this !== self::CreatedAt && $this !== self::UpdatedAt;
    }
}
