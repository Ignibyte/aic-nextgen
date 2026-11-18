<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

/**
 * What a TableGuard refuses (#310).
 */
enum GuardKind
{
    /** Every UPDATE, DELETE and TRUNCATE. */
    case AppendOnly;

    /** Changing a guarded column once it holds a value, and deleting a row where one does. */
    case FinalOnce;
}
