<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What the guard's record says of a filed ledger (#103, Droost's three
 * answers): an `aic-seeker` returned it; the guard records no returns here;
 * or none returned, for a self-reviewed ledger.
 */
enum SeekerDispatch: string
{
    case Verified = 'verified';
    case Unverified = 'unverified';
    case None = 'none';
}
