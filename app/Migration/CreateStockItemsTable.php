<?php

declare(strict_types=1);

namespace App\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The stock demo's items: a name, a price with cents, the date the item came
 * in, and whether it is certified.
 */
final readonly class CreateStockItemsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000005_create_stock_items';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('stock_items')
            ->identity('id')
            ->string('name', 100)
            ->numeric('price', 12, 2)
            ->date('added_on')
            ->boolean('certified')
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
