<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Cache\Domain\CacheKey;
use AIC\Cache\Domain\RateLimit;
use AIC\Cache\Domain\RateLimits;
use InvalidArgumentException;

/**
 * The app's rate limits (#312), a factory the manifest lists, as the account
 * module's sign-in rules are: `/ledger/limited` takes two hits per client an
 * hour.
 */
final readonly class LedgerLimits
{
    /**
     * @throws InvalidArgumentException When a limit is declared wrongly.
     *
     * @return RateLimits What `RateLimitMiddleware` counts.
     */
    public function rateLimits(): RateLimits
    {
        return new RateLimits([new RateLimit(new CacheKey('ledger-limited'), '/ledger/limited', 2, 3_600)]);
    }
}
