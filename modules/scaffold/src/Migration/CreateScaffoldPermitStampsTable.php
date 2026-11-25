<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_permit_stamps` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldPermitStampsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_25_000002_create_scaffold_permit_stamps_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_permit_stamps');
        $this->scaffoldPermitStampsColumns($tableBuilder);
        $this->scaffoldPermitStampsConstraints($tableBuilder);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldPermitStampsColumns(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->identity('id')
            ->bigInteger('permit_id')
            ->text('permit_zone')
            ->text('batch')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldPermitStampsConstraints(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->primary(['id'])
            ->references(['permit_id', 'permit_zone'], 'scaffold_permits', ['id', 'zone'], OnDelete::Cascade)
            ->uniqueWithNulls(['permit_id', 'batch'])
            ->index(['batch']);
    }
}
