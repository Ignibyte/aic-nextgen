<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * How `Expressions::compare()` compares two terms (#310).
 */
enum Operator
{
    /** The two are equal. */
    case Equal;

    /** The two differ. */
    case NotEqual;

    /** The left is less than the right. */
    case Less;

    /** The left is at most the right. */
    case LessOrEqual;

    /** The left is greater than the right. */
    case Greater;

    /** The left is at least the right. */
    case GreaterOrEqual;
}
