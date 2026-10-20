<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

/**
 * What a `Range` request comes to for one resource.
 */
enum RangeKind
{
    /** No usable range: send the whole resource (200). */
    case Whole;

    /** One satisfiable range: send that part (206). */
    case Part;

    /** A range the resource cannot satisfy (416). */
    case Unsatisfiable;
}
