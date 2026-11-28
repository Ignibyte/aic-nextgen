<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use AIC\Schema\Contracts\Migration;

/**
 * The authorization server's migrations, in the order they apply: its
 * clients, codes and tokens (#331), then refresh tokens, families and
 * audiences (#352).
 */
final readonly class OAuthMigrations
{
    /**
     * @return list<Migration> Each migration, in order.
     */
    public function all(): array
    {
        return [new CreateOAuthTables(), new AddOAuthLifecycleTables()];
    }
}
