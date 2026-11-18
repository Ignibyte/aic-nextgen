<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Cache\Domain\CacheKey;
use AIC\Cache\Domain\RateDecision;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;

use function strlen;

/**
 * Counts hits against a limit in Postgres (#312), in the unlogged
 * `aic_rate_limits`: a client's window opens at its first hit and lasts the
 * limit's seconds, and the first hit after it opens a new one. One upsert per
 * hit, so concurrent hits never lose a count.
 */
final readonly class RateLimiter
{
    /**
     * @param Database $database The database, with the cache tables.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $name    The limit's name.
     * @param string $client  Who hits, such as an address or a user id: 1 to 128 bytes.
     * @param int    $limit   The hits allowed in a window, one or more.
     * @param int    $seconds The window's length, one second or more.
     *
     * @throws InvalidArgumentException When the name or the client is malformed, or the limit or window not positive.
     * @throws QueryException           When the count fails.
     *
     * @return RateDecision Whether this hit is allowed, and the seconds left in the window.
     */
    public function hit(string $name, string $client, int $limit, int $seconds): RateDecision
    {
        if ($client === '' || strlen($client) > 128 || $limit < 1 || $seconds < 1) {
            throw new InvalidArgumentException(
                'A rate limit counts a client of 1 to 128 bytes, with a limit and a window of at least one.',
            );
        }

        $row = $this->database->fetchOne(
            "INSERT INTO aic_rate_limits (name, client, hits, window_ends_at) VALUES (?, ?, 1, now() + ? * interval '1 second')"
                . ' ON CONFLICT (name, client) DO UPDATE SET'
                . ' hits = CASE WHEN aic_rate_limits.window_ends_at > now() THEN aic_rate_limits.hits + 1 ELSE 1 END,'
                . ' window_ends_at = CASE WHEN aic_rate_limits.window_ends_at > now() THEN aic_rate_limits.window_ends_at'
                . ' ELSE EXCLUDED.window_ends_at END'
                . ' RETURNING hits, GREATEST(1, ceil(extract(epoch FROM window_ends_at - now())))::integer AS retry_after',
            [(new CacheKey($name))->value, $client, $seconds],
        );
        return new RateDecision($row->int('hits') <= $limit, $row->int('retry_after'));
    }

    /**
     * @param string $name A limit's name.
     *
     * @throws InvalidArgumentException When the name is malformed.
     * @throws QueryException           When the delete fails.
     *
     * @return int How many clients' counts it cleared.
     */
    public function reset(string $name): int
    {
        return $this->database->execute('DELETE FROM aic_rate_limits WHERE name = ?', [(new CacheKey($name))->value]);
    }

    /**
     * @throws QueryException When a delete fails.
     *
     * @return int How many closed windows it removed.
     */
    public function prune(): int
    {
        return $this->database->execute('DELETE FROM aic_rate_limits WHERE window_ends_at <= now()', []);
    }
}
