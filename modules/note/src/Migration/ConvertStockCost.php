<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Changes the stock items' cost to dollars and cents and their count date to
 * a date, as Lotline's vehicles need (#86): the second converts each value
 * through its UTC date, which Postgres cannot cast on its own.
 */
final readonly class ConvertStockCost implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000008_convert_stock_cost';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $schema->alter('stock_items')
            ->changeType('cost', $columnTypes->numeric(10, 2))
            ->changeType('counted_on', $columnTypes->date(), "(counted_on AT TIME ZONE 'UTC')::date");
    }
}
