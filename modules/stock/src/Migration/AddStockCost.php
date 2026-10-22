<?php

declare(strict_types=1);

namespace App\Stock\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Adds a cost and a count date to the stock items, in the types Lotline's
 * vehicles first held them: whole dollars and a UTC-midnight timestamp
 * (#86). ConvertStockCost changes them.
 */
final readonly class AddStockCost implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000007_add_stock_cost';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $schema->alter('stock_items')
            ->addColumn('cost', $columnTypes->integer())
            ->nullable()
            ->addColumn('counted_on', $columnTypes->timestampTz())
            ->nullable();
    }
}
