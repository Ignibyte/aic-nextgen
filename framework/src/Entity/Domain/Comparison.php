<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * How a condition compares a field with its value (#184): its case is the SQL
 * operator. Against null only Equals (`IS NULL`) and NotEquals (`IS NOT NULL`)
 * apply. A strict `<` or `>` joins with its first caller.
 */
enum Comparison: string
{
    case Equals = '=';
    case NotEquals = '<>';
    case LessOrEqual = '<=';
    case GreaterOrEqual = '>=';
}
