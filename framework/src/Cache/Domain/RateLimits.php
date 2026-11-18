<?php

declare(strict_types=1);

namespace AIC\Cache\Domain;

/**
 * The app's rate limits (#312), as one module's factory gives them to
 * `RateLimitMiddleware`: every limit whose prefix a path starts with counts
 * the hit.
 */
final readonly class RateLimits
{
    /**
     * @param list<object> $limits The RateLimits: objects, the list form elephc keeps (plan.md).
     */
    public function __construct(
        private array $limits,
    ) {
    }

    /**
     * @param string $path A request's path.
     *
     * @return list<RateLimit> The limits that guard it, in order.
     */
    public function guarding(string $path): array
    {
        $guarding = [];

        foreach ($this->limits as $limit) {
            if (!$limit instanceof RateLimit || !$limit->guards($path)) {
                continue;
            }

            $guarding[] = $limit;
        }

        return $guarding;
    }
}
