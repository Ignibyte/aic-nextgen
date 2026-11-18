<?php

declare(strict_types=1);

namespace AIC\Cache\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A cache entry's key, a lock's name or a rate limit's name (#312): 1 to 200
 * letters, digits and `:`, `.`, `_` or `-`, so a module namespaces its keys
 * as `module:what:id`.
 */
final readonly class CacheKey
{
    /**
     * @param string $value The key.
     *
     * @throws InvalidArgumentException When it is not 1 to 200 of those characters.
     */
    public function __construct(
        public string $value,
    ) {
        if (preg_match('/^[A-Za-z0-9:._-]{1,200}$/', $value) !== 1) {
            throw new InvalidArgumentException('"' . $value . '" is not a cache key: 1 to 200 letters, digits, :, ., _ or -.');
        }
    }
}
