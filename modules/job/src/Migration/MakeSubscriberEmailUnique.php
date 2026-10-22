<?php

declare(strict_types=1);

namespace App\Job\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * One subscriber per email, added to the table an earlier migration created.
 */
final readonly class MakeSubscriberEmailUnique implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000004_make_subscriber_email_unique';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('subscribers')
            ->unique(['email']);
    }
}
