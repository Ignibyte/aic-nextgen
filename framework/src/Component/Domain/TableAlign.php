<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * Where a table column's text sits (#240): at the start of the cell, or at its
 * end, as money and counts do. End columns carry the class `aic-align-end`,
 * which the theme styles.
 */
enum TableAlign: string
{
    case Start = 'start';
    case End = 'end';
}
