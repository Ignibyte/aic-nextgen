<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `note_marks` table, made by bin/aic make:entity (#123) for NoteMark.
 */
final readonly class CreateNoteMarksTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000001_create_note_marks_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('note_marks')
            ->column('id', $schema->types()->uuidKey())
            ->text('label')
            ->column('kind', $schema->types()->choice(['pin', 'flag']))
            ->column('meta', $schema->types()->jsonb())
            ->timestampTz('marked_at')
            ->column('origin', $schema->types()->uuid())
            ->numeric('weight', 12, 2)
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
