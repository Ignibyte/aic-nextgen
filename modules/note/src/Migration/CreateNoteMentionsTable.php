<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `note_mentions` table, made by bin/aic make:migration (#122): a note's
 * mention of a note or a stock item, by type and id, the fixture's
 * polymorphic relation (#306).
 */
final readonly class CreateNoteMentionsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000003_create_note_mentions_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('note_mentions')
            ->identity('id')
            ->bigInteger('note_id')
            ->column('subject_type', $schema->types()->choice(['note', 'stock_item']))
            ->bigInteger('subject_id')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->foreignKey('note_id', 'notes', 'id', OnDelete::Cascade);
    }
}
