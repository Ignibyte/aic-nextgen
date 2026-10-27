<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `stock_days` view (#191): per day the stock came in, how many items
 * and what they are worth. The stock report reads it through a read-only
 * entity type.
 */
final readonly class CreateStockDaysView implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_28_000010_create_stock_days_view';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view(
            'stock_days',
            'SELECT added_on, count(*)::integer AS items, sum(price) AS worth FROM stock_items GROUP BY added_on',
        );
    }
}
