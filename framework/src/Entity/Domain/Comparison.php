<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * How a condition compares a field with its value (#184): the plain
 * operators, and since #311 a Json field holding a document
 * (`Contains`), text equal without case (`EqualsIgnoringCase`) and text alike
 * by trigrams (`Similar`, pg_trgm's `%` at its threshold). Against null only
 * Equals (`IS NULL`) and NotEquals (`IS NOT NULL`) apply. A strict `<` or `>`
 * joins with its first caller.
 */
enum Comparison: string
{
    case Equals = '=';
    case NotEquals = '<>';
    case LessOrEqual = '<=';
    case GreaterOrEqual = '>=';
    case Contains = '@>';
    case EqualsIgnoringCase = 'lower';
    case Similar = '%';
}
