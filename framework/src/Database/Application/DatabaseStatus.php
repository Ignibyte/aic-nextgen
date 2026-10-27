<?php

declare(strict_types=1);

namespace AIC\Database\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;

/**
 * What a site asks the database about itself (#186): whether it answers, its
 * version, and a setting. A module reads tables through the entity API and
 * the database's own state through this, never the raw `Database` seam.
 */
final readonly class DatabaseStatus
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @throws QueryException When the database does not answer.
     */
    public function ping(): void
    {
        $this->database->fetchOne('SELECT 1 AS up', []);
    }

    /**
     * @throws QueryException When the database does not answer.
     *
     * @return string The server's version line.
     */
    public function version(): string
    {
        return $this->database->fetchOne('SELECT version() AS version', [])->string('version');
    }

    /**
     * @param string $name A server setting, such as `server_version`.
     *
     * @throws QueryException When the database does not answer, or has no such setting.
     *
     * @return string Its value.
     */
    public function setting(string $name): string
    {
        return $this->database->fetchOne('SELECT current_setting(?) AS value', [$name])->string('value');
    }
}
