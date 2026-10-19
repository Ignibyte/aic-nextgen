<?php

declare(strict_types=1);

/*
 * The database. DDEV's web container exports the libpq variables (PGHOST,
 * PGUSER, PGPASSWORD, PGDATABASE); the defaults reach DDEV's Postgres from the
 * host.
 */

use AIC\Database\Domain\DatabaseConfig;

$setting = static function (string $name, string $default): string {
    $value = getenv($name);

    return is_string($value) && $value !== ''
        ? $value
        : $default;
};

return new DatabaseConfig(
    host: $setting('PGHOST', '127.0.0.1'),
    port: (int) $setting('PGPORT', '5432'),
    database: $setting('PGDATABASE', 'db'),
    user: $setting('PGUSER', 'db'),
    password: $setting('PGPASSWORD', 'db'),
);
