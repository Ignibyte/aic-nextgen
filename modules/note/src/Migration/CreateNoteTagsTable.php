<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * A note's tags: each tag once per note, found by tag through an index, and
 * deleted with its note.
 */
final readonly class CreateNoteTagsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000001_create_note_tags';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('note_tags')
            ->identity('id')
            ->bigInteger('note_id')
            ->string('tag', 50)
            ->primary(['id'])
            ->unique(['note_id', 'tag'])
            ->index(['tag'])
            ->foreignKey('note_id', 'notes', 'id', OnDelete::Cascade);
    }
}
