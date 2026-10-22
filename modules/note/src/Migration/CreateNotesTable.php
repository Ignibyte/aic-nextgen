<?php

declare(strict_types=1);

namespace App\Note\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The demo app's first table: notes, each with a title, an optional body and
 * when it was written.
 */
final readonly class CreateNotesTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_19_000001_create_notes';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('notes')
            ->identity('id')
            ->string('title', 200)
            ->text('body')
            ->nullable()
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
