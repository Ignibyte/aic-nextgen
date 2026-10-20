<?php

declare(strict_types=1);

namespace AIC\Session\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Session\Contracts\SessionStore;
use Override;

/**
 * Sessions in Postgres, in `aic_sessions` (CreateSessionsTable). A row past
 * its `expires_at` is never read; removing such rows waits for jobs (#13).
 */
final readonly class PostgresSessionStore implements SessionStore
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $id A session id.
     *
     * @throws QueryException When the query fails.
     *
     * @return string|null Its payload; null when there is none or it has expired.
     */
    #[Override]
    public function read(string $id): ?string
    {
        $payload = $this->database->fetchOne(
            "SELECT coalesce(max(payload), '') AS payload FROM aic_sessions WHERE id = ? AND expires_at > now()",
            [$id],
        )->string('payload');

        return $payload === ''
            ? null
            : $payload;
    }

    /**
     * @param string $id      A session id.
     * @param string $payload Its payload.
     * @param int    $ttl     Seconds it lives from now.
     *
     * @throws QueryException When the statement fails.
     */
    #[Override]
    public function write(string $id, string $payload, int $ttl): void
    {
        $this->database->execute(
            'INSERT INTO aic_sessions (id, payload, expires_at) VALUES (?, ?, now() + make_interval(secs => ?))'
            . ' ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload, expires_at = EXCLUDED.expires_at',
            [$id, $payload, $ttl],
        );
    }

    /**
     * @param string $id A session id to forget.
     *
     * @throws QueryException When the statement fails.
     */
    #[Override]
    public function destroy(string $id): void
    {
        $this->database->execute('DELETE FROM aic_sessions WHERE id = ?', [$id]);
    }
}
