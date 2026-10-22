<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Who says they read the run (#103): an independent seeker, or the coding
 * agent reviewing itself, which is recorded and never counted.
 */
enum SeekerInspector: string
{
    case Independent = 'independent';
    case SelfReviewed = 'self-reviewed';
}
