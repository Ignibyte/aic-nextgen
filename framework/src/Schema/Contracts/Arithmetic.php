<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * What `Expressions::calculate()` does with two terms (#310).
 */
enum Arithmetic
{
    /** The left plus the right. */
    case Plus;

    /** The left minus the right. */
    case Minus;

    /** The left times the right. */
    case Times;

    /** The left divided by the right. */
    case DividedBy;
}
