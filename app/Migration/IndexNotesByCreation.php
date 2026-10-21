<?php

declare(strict_types=1);

namespace App\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Indexes the notes by when they were written: the demo's first change to a
 * table an earlier migration created.
 */
final readonly class IndexNotesByCreation implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000002_index_notes_by_creation';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('notes')
            ->index(['created_at']);
    }
}
