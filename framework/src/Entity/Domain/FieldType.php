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
}
