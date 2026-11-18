<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Cache\Domain\CacheKey;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Closure;
use InvalidArgumentException;

/**
 * A key-value cache in Postgres (#312): text values by key, each until it
 * expires, in the unlogged `aic_cache` (CreateCacheTables), so a crash
 * empties it. A module encodes its own values; a counter is one atomic
 * upsert. Expired entries read as missing; `cache:prune` removes them.
 */
final readonly class PostgresCache
{
    /**
     * @param Database $database The database, with the cache tables.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $key A key, such as `ledger:totals:2026-11`.
     *
     * @throws InvalidArgumentException When the key is malformed.
     * @throws QueryException           When the read fails.
     *
     * @return string|null Its value; null when it is missing or expired.
     */
    public function get(string $key): ?string
    {
        $sql = 'SELECT value FROM aic_cache WHERE key = ? AND expires_at > now()';

        foreach ($this->database->fetchAll($sql, [(new CacheKey($key))->value]) as $row) {
            return $row->string('value');
        }

        return null;
    }

    /**
     * @param string $key     A key.
     * @param string $value   Its value.
     * @param int    $seconds How long it lives, one second or more.
     *
     * @throws InvalidArgumentException When the key is malformed or the time is not positive.
     * @throws QueryException           When the write fails.
     */
    public function put(string $key, string $value, int $seconds): void
    {
        $this->database->execute(
            "INSERT INTO aic_cache (key, value, expires_at) VALUES (?, ?, now() + ? * interval '1 second')"
                . ' ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, expires_at = EXCLUDED.expires_at',
            [(new CacheKey($key))->value, $value, self::seconds($seconds)],
        );
    }

    /**
     * @param string $key A key.
     *
     * @throws InvalidArgumentException When the key is malformed.
     * @throws QueryException           When the delete fails.
     */
    public function forget(string $key): void
    {
        $this->database->execute('DELETE FROM aic_cache WHERE key = ?', [(new CacheKey($key))->value]);
    }

    /**
     * @param string            $key     A key.
     * @param int               $seconds How long a computed value lives.
     * @param Closure(): string $compute Makes the value on a miss.
     *
     * @throws InvalidArgumentException When the key is malformed or the time is not positive.
     * @throws QueryException           When the read or the write fails.
     *
     * @return string The cached value, or the computed one, now cached.
     */
    public function remember(string $key, int $seconds, Closure $compute): string
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $compute();
        $this->put($key, $value, $seconds);

        return $value;
    }

    /**
     * @param string $key     A counter's key.
     * @param int    $amount  What to add, negative to count down.
     * @param int    $seconds How long a new counter lives; adding keeps a live counter's expiry.
     *
     * @throws InvalidArgumentException When the key is malformed or the time is not positive.
     * @throws QueryException           When the write fails, or the key holds a value that is not a whole number.
     *
     * @return int The count after adding, in one statement, so two writers never lose a hit.
     */
    public function increment(string $key, int $amount, int $seconds): int
    {
        $sql = "INSERT INTO aic_cache (key, value, expires_at) VALUES (?, ?::bigint::text, now() + ? * interval '1 second')"
            . ' ON CONFLICT (key) DO UPDATE SET'
            . ' value = CASE WHEN aic_cache.expires_at > now()'
            . ' THEN (aic_cache.value::bigint + EXCLUDED.value::bigint)::text ELSE EXCLUDED.value END,'
            . ' expires_at = CASE WHEN aic_cache.expires_at > now() THEN aic_cache.expires_at ELSE EXCLUDED.expires_at END'
            . ' RETURNING value::bigint AS value';

        return $this->database->fetchOne($sql, [(new CacheKey($key))->value, $amount, self::seconds($seconds)])->int('value');
    }

    /**
     * @throws QueryException When the delete fails.
     *
     * @return int How many expired entries it removed.
     */
    public function prune(): int
    {
        return $this->database->execute('DELETE FROM aic_cache WHERE expires_at <= now()', []);
    }

    /**
     * @param int $seconds A lifetime.
     *
     * @throws InvalidArgumentException When it is not positive.
     *
     * @return int It.
     */
    private static function seconds(int $seconds): int
    {
        return $seconds >= 1
            ? $seconds
            : throw new InvalidArgumentException('A cache entry lives one second or more.');
    }
}
