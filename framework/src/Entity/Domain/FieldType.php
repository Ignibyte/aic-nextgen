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

    /**
     * @return bool Whether a caller writes it: every type but CreatedAt.
     */
    public function isWritable(): bool
    {
        return $this !== self::CreatedAt;
    }
}
