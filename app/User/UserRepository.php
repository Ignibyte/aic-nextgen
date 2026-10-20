<?php

declare(strict_types=1);

namespace App\User;

use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Override;

/**
 * The demo's users table, as the Authenticator reads it (#9). A user's id is
 * their login, the table's key.
 */
final readonly class UserRepository implements UserProvider
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $login What the user signs in with.
     *
     * @throws QueryException When the query fails.
     *
     * @return UserCredentials|null Their credentials; null when there is no such user.
     */
    #[Override]
    public function credentials(string $login): ?UserCredentials
    {
        $hash = $this->database->fetchOne(
            "SELECT coalesce(max(password_hash), '') AS password_hash FROM users WHERE login = ?",
            [$login],
        )->string('password_hash');

        return $hash === ''
            ? null
            : new UserCredentials($login, $hash);
    }

    /**
     * Creates the user, or gives an existing one a new password.
     *
     * @param string $login        What the user signs in with.
     * @param string $passwordHash The hash of their password.
     *
     * @throws QueryException When the statement fails.
     */
    public function save(string $login, string $passwordHash): void
    {
        $this->database->execute(
            'INSERT INTO users (login, password_hash, created_at) VALUES (?, ?, now())'
            . ' ON CONFLICT (login) DO UPDATE SET password_hash = EXCLUDED.password_hash',
            [$login, $passwordHash],
        );
    }
}
