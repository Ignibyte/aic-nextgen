<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `scaffold_items` table, made by bin/aic make:migration (#122).
 */
final readonly class CreateScaffoldItemsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_23_000000_create_scaffold_items_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('scaffold_items')
            ->identity('id')
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
