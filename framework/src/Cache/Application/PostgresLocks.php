<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Cache\Domain\CacheKey;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Closure;
use LogicException;
use Throwable;

/**
 * Named locks over Postgres advisory locks (#312), keyed by
 * `hashtextextended(name, 0)`: one held to the end of the open transaction,
 * or one held around a closure and released however it ends. Every worker
 * and every request sees the same locks, since they live in the database.
 */
final readonly class PostgresLocks
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * Waits for the lock and holds it until the open transaction commits or rolls back.
     *
     * @param string $name The lock's name, such as `ledger:close-month`.
     *
     * @throws LogicException When no transaction is open, or the name is malformed.
     * @throws QueryException When the lock cannot be taken.
     */
    public function holdForTransaction(string $name): void
    {
        if (!$this->database->inTransaction()) {
            throw new LogicException('A transaction lock needs an open transaction: take it inside EntityStore::transaction().');
        }

        $this->database->execute('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [(new CacheKey($name))->value]);
    }

    /**
     * @param string          $name The lock's name.
     * @param Closure(): void $work What to do while holding it, after waiting for it.
     *
     * @throws Throwable Whatever the work throws, after the lock is released.
     */
    public function around(string $name, Closure $work): void
    {
        $key = (new CacheKey($name))->value;
        $this->database->execute('SELECT pg_advisory_lock(hashtextextended(?, 0))', [$key]);

        try {
            $work();
        } finally {
            $this->database->execute('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$key]);
        }
    }

    /**
     * @param string          $name The lock's name.
     * @param Closure(): void $work What to do while holding it; it does not run when another holds the lock.
     *
     * @throws Throwable Whatever the work throws, after the lock is released.
     *
     * @return bool Whether the lock was free, so the work ran.
     */
    public function tryAround(string $name, Closure $work): bool
    {
        $key = (new CacheKey($name))->value;

        if (!$this->database->fetchOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS locked', [$key])->bool('locked')) {
            return false;
        }

        try {
            $work();
        } finally {
            $this->database->execute('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$key]);
        }

        return true;
    }
}
