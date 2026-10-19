<?php

declare(strict_types=1);

namespace AIC\Database\Domain;

use InvalidArgumentException;

use function trim;

/**
 * Where the database is, as `config/database.php` returns it: a Postgres host,
 * port, database name and credentials.
 */
final readonly class DatabaseConfig
{
    /**
     * @param string $host     The server's host name.
     * @param int    $port     Its port.
     * @param string $database The database name.
     * @param string $user     The user to connect as.
     * @param string $password That user's password.
     *
     * @throws InvalidArgumentException When the host or the database name is blank, or the port is out of range.
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $user,
        public string $password,
    ) {
        if (trim($host) === '' || trim($database) === '' || $port < 1 || $port > 65_535) {
            throw new InvalidArgumentException('DatabaseConfig: a host, a port of 1 to 65535 and a database name are required.');
        }
    }
}
