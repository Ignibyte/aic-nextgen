<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `ledger_categories` table, made by bin/aic make:migration (#122): a
 * tree of ledger categories (#311), each under its parent, with JSON traits.
 */
final readonly class CreateLedgerCategoriesTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_091722_create_ledger_categories_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('ledger_categories')
            ->identity('id')
            ->text('name')
            ->bigInteger('parent_id')
            ->nullable()
            ->column('traits', $schema->types()->jsonb())
            ->timestampTz('created_at')
            ->primary(['id'])
            ->foreignKey('parent_id', 'ledger_categories', 'id', OnDelete::Cascade);
    }
}
