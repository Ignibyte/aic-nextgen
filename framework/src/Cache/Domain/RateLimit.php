<?php

declare(strict_types=1);

namespace AIC\Cache\Domain;

use InvalidArgumentException;

use function str_starts_with;

/**
 * One rate limit (#312): its name, the path prefix it guards, how many hits a
 * client may make, and the window those hits count in, which opens at the
 * client's first hit.
 */
final readonly class RateLimit
{
    /**
     * @param CacheKey $name    Its name, the key its counts are kept under.
     * @param string   $prefix  The path prefix it guards, such as `/api/`.
     * @param int      $limit   The hits a client may make in a window, one or more.
     * @param int      $seconds The window's length, one or more seconds.
     *
     * @throws InvalidArgumentException When the prefix is not a path, or the limit or the window is not positive.
     */
    public function __construct(
        public CacheKey $name,
        public string $prefix,
        public int $limit,
        public int $seconds,
    ) {
        if (!str_starts_with($prefix, '/') || $limit < 1 || $seconds < 1) {
            throw new InvalidArgumentException('Rate limit ' . $name->value . ': a path prefix, and a limit and a window of at least one.');
        }
    }

    /**
     * @param string $path A request's path.
     *
     * @return bool Whether the limit guards it.
     */
    public function guards(string $path): bool
    {
        return str_starts_with($path, $this->prefix);
    }
}
