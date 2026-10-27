<?php

declare(strict_types=1);

namespace App\Account\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Gives `users` a key the database generates (#186), so the account module
 * reads and writes its users through an entity type. The login stays the
 * primary key users sign in by.
 */
final readonly class AddUserIds implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_28_000011_add_user_ids';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('users')
            ->addColumn('id', $schema->types()->identity());
    }
}
