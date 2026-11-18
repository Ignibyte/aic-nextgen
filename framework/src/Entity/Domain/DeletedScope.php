<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * Which rows of a soft-deleting entity type a query sees (#309).
 */
enum DeletedScope
{
    /** The live rows only: the default. */
    case Live;

    /** Every row, live or deleted. */
    case All;

    /** The deleted rows only. */
    case Deleted;
}
