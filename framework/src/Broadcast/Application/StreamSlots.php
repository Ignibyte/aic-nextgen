<?php

declare(strict_types=1);

namespace AIC\Broadcast\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;

/**
 * The site-wide cap on open broadcast streams (#314): a stream holds one of
 * `streams` Postgres session advisory locks on its request's own connection,
 * which Postgres frees when the request ends and the connection closes,
 * a crashed one included.
 */
final readonly class StreamSlots
{
    /**
     * The advisory locks' first key, which no other AIC lock uses.
     */
    private const int KEY = 314;

    /**
     * @param Database $database The request's database connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param int $streams How many streams may be open at once.
     *
     * @throws QueryException When a lock cannot be asked for.
     *
     * @return bool Whether this request took a slot; false when every one is held.
     */
    public function claim(int $streams): bool
    {
        for ($slot = 1; $slot <= $streams; $slot += 1) {
            $row = $this->database->fetchOne('SELECT pg_try_advisory_lock(?, ?) AS held', [self::KEY, $slot]);

            if ($row->bool('held')) {
                return true;
            }
        }

        return false;
    }
}
