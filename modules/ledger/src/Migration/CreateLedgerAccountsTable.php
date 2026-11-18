<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `ledger_accounts` table, made by bin/aic make:migration (#122): an
 * audited, soft-deleting account (#309). A name is unique among the live
 * accounts only, so a deleted account's name can be taken again.
 */
final readonly class CreateLedgerAccountsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_062716_create_ledger_accounts_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $expressions = $schema->expressions();
        $schema->create('ledger_accounts')
            ->identity('id')
            ->text('name')
            ->timestampTz('deleted_at')
            ->nullable()
            ->timestampTz('created_at')
            ->primary(['id'])
            ->uniqueWhere(['name'], $expressions->isNull($expressions->column('deleted_at')));
    }
}
