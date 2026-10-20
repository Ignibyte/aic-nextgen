<?php

declare(strict_types=1);

namespace App\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The demo's users (#9): a login, unique as the key, and a bcrypt hash.
 */
final readonly class CreateUsersTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000003_create_users';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('users')
            ->string('login', 100)
            ->text('password_hash')
            ->timestampTz('created_at')
            ->primary(['login']);
    }
}
