<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

/**
 * A column's type, independent of the database: a dialect compiler names it.
 */
enum LogicalType
{
    /** An integer key the database generates, one per row. */
    case BigIdentity;

    /** Text up to a length. */
    case String;

    /** Text of any length. */
    case Text;

    /** A point in time with its time zone. */
    case TimestampTz;

    /** A whole number, 32 bits. */
    case Integer;
}
