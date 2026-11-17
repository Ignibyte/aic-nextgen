<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `notes_stock_items` pivot between `notes` and `stock_items`, made by bin/aic
 * make:relation (#306): each row links one `notes` row to one `stock_items`
 * row, once, with fields of its own; deleting either row deletes the link.
 */
final readonly class CreateNotesStockItemsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000002_create_notes_stock_items_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('notes_stock_items')
            ->identity('id')
            ->bigInteger('note_id')
            ->bigInteger('stock_item_id')
            ->numeric('amount', 12, 2)
            ->primary(['id'])
            ->unique(['note_id', 'stock_item_id'])
            ->foreignKey('note_id', 'notes', 'id', OnDelete::Cascade)
            ->foreignKey('stock_item_id', 'stock_items', 'id', OnDelete::Cascade);
    }
}
