<?php

declare(strict_types=1);

namespace App\Account\Repository;

use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;
use Override;

use function substr;

/**
 * The demo's users table, as the Authenticator reads it (#9), through the
 * entity API (#186). Users sign in by their login, the table's primary key;
 * `id` is the key the entity type needs.
 */
final readonly class UserRepository implements UserProvider
{
    /**
     * The `users` table: a login, a password hash and when the user was made.
     */
    private EntityType $entityType;

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws InvalidArgumentException When the type is declared wrongly.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
        $this->entityType = new EntityType('users', 'id', [
            new Field('login', FieldType::Text),
            new Field('password_hash', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @param string $login What the user signs in with.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a field is declared wrongly.
     *
     * @return UserCredentials|null Their credentials; null when there is no such user.
     */
    #[Override]
    public function credentials(string $login): ?UserCredentials
    {
        $row = $this->entityStore->query($this->entityType)->where('login', $login)->first();

        return $row instanceof Row
            ? new UserCredentials($login, $row->string('password_hash'), ['joined' => substr($row->string('created_at'), 0, 10)])
            : null;
    }

    /**
     * Creates the user, or gives an existing one a new password.
     *
     * @param string $login        What the user signs in with.
     * @param string $passwordHash The hash of their password.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function save(string $login, string $passwordHash): void
    {
        $this->entityStore->upsert($this->entityType, ['login' => $login, 'password_hash' => $passwordHash], 'login');
    }
}
