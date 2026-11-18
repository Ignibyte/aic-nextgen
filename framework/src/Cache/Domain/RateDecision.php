<?php

declare(strict_types=1);

namespace AIC\Cache\Domain;

/**
 * What a rate limit said of one hit (#312): whether it is allowed, and the
 * seconds until the window ends.
 */
final readonly class RateDecision
{
    /**
     * @param bool $allowed    Whether the hit is within the limit.
     * @param int  $retryAfter The seconds until the window ends and the count starts again.
     */
    public function __construct(
        public bool $allowed,
        public int $retryAfter,
    ) {
    }
}
