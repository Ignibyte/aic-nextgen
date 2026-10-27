<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Gives the stock items an optional SKU, unique where it is set, a quantity
 * and the time a row last changed (#184), so the stock form saves an item by
 * its SKU through the entity store's upsert. Rows without a SKU stay as they
 * were: a unique column holds any number of nulls.
 */
final readonly class AddStockQuantity implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_28_000009_add_stock_quantity';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $schema->alter('stock_items')
            ->addColumn('sku', $columnTypes->string(40))
            ->nullable()
            ->addColumn('quantity', $columnTypes->integer())
            ->nullable()
            ->addColumn('updated_at', $columnTypes->timestampTz())
            ->nullable()
            ->unique(['sku']);
    }
}
