<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * What a foreign key does to the rows that refer to a row being deleted.
 */
enum OnDelete
{
    /** The delete fails while a row refers to it. */
    case Restrict;

    /** The rows that refer to it are deleted with it. */
    case Cascade;

    /** The referring column is set to null; the column must be nullable. */
    case SetNull;
}
