<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The exports' table `scaffold_shelf_exports` and its view `scaffold_shelf_export_states`, made by
 * bin/aic make:export (#323): each export's uuid and when it was asked
 * for, and whether 7 days have passed since by the database's `now()`.
 */
final readonly class CreateScaffoldShelfExportsTable implements Migration
{
    /**
     * Each export, and whether it is past its days.
     */
    private const string STATES = "SELECT id, (created_at < now() - interval '7 days') AS expired FROM scaffold_shelf_exports";

    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_20_000000_create_scaffold_shelf_exports_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('scaffold_shelf_exports')
            ->column('id', $schema->types()->uuidKey())
            ->timestampTz('created_at')
            ->primary(['id']);
        $schema->view('scaffold_shelf_export_states', self::STATES);
    }
}
