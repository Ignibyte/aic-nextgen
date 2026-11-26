<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_crews` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldCrewsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_25_000003_create_scaffold_crews_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_crews');
        $this->scaffoldCrewsColumns($tableBuilder);
        $this->scaffoldCrewsConstraints($tableBuilder);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldCrewsColumns(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->identity('id')
            ->text('name')
            ->bigInteger('lead_shift_id')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldCrewsConstraints(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->primary(['id']);
    }
}
