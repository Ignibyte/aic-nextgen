<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * Which way a window orders its rows (#311).
 */
enum Direction
{
    /** Smallest first. */
    case Ascending;

    /** Largest first. */
    case Descending;
}
