<?php

declare(strict_types=1);

namespace App\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Newsletter subscribers, one email each. Created without its unique email,
 * which a later migration adds: the shape of a site that learns a rule after
 * its table is in use.
 */
final readonly class CreateSubscribersTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000003_create_subscribers';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('subscribers')
            ->identity('id')
            ->string('email', 254)
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
