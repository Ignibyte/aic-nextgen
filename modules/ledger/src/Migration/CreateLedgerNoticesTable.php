<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `ledger_notices` table, made by bin/aic make:migration (#122): the
 * lines the account listener's job writes (#309), read once by
 * GET /ledger/notices.
 */
final readonly class CreateLedgerNoticesTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_062716_create_ledger_notices_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('ledger_notices')
            ->identity('id')
            ->text('line')
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
