<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Where a seeker finding stands (#103): open, resolved by a later change, or
 * carried with the seeker's reason.
 */
enum SeekerStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Carried = 'carried';
}
