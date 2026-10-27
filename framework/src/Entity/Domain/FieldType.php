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

    /** A calendar date, written and read as a `YYYY-MM-DD` string. */
    case Date;

    /** An exact decimal, written and read as its decimal string: money never passes through a float. */
    case Numeric;

    /** True or false, written and read as a bool. */
    case Boolean;

    /** A whole number, written and read as an int (#184). */
    case Integer;

    /** When the row last changed: `now()` at insert and at every update, never written by hand (#184). */
    case UpdatedAt;

    /**
     * @return bool Whether a caller writes it: every type but CreatedAt and UpdatedAt.
     */
    public function isWritable(): bool
    {
        return $this !== self::CreatedAt && $this !== self::UpdatedAt;
    }
}
