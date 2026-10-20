<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Override;
use SensitiveParameter;

use function hash_equals;

/**
 * Hashes passwords in Postgres with pgcrypto (#9): bcrypt at cost 12, as
 * `$2a$12$...`, which PHP's `password_verify` also reads. elephc has no
 * `password_hash` yet; the hashes stay valid when one replaces this.
 */
final readonly class PgcryptoPasswordHasher implements PasswordHasher
{
    /**
     * @param Database $database The database, with pgcrypto (EnablePgcrypto).
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $password A password.
     *
     * @throws QueryException When Postgres cannot hash it.
     *
     * @return string Its bcrypt hash.
     */
    #[Override]
    public function hash(#[SensitiveParameter]
        string $password, ): string
    {
        return $this->database->fetchOne("SELECT crypt(?, gen_salt('bf', 12)) AS hash", [$password])->string('hash');
    }

    /**
     * @param string $password A password.
     * @param string $hash     A stored bcrypt hash.
     *
     * @throws QueryException When Postgres cannot hash it.
     *
     * @return bool Whether the password is the hash's, compared in constant time.
     */
    #[Override]
    public function verify(#[SensitiveParameter]
        string $password, string $hash, ): bool
    {
        if ($hash === '') {
            return false;
        }

        return hash_equals($hash, $this->database->fetchOne('SELECT crypt(?, ?) AS hash', [$password, $hash])->string('hash'));
    }
}
