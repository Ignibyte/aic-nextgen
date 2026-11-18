<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * What a `Window` numbers its rows by (#311).
 */
enum WindowFunction
{
    /** 1, 2, 3, … in order, ties broken by the database. */
    case RowNumber;

    /** Equal rows share a rank, and the next rank skips: 1, 1, 3. */
    case Rank;

    /** Equal rows share a rank, and the next does not skip: 1, 1, 2. */
    case DenseRank;
}
