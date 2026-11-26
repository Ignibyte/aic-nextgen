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
 * The `scaffold_shifts` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldShiftsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_25_000004_create_scaffold_shifts_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_shifts');
        $this->scaffoldShiftsColumns($tableBuilder);
        $this->scaffoldShiftsConstraints($tableBuilder);
        $schema->alter('scaffold_crews')
            ->references(['lead_shift_id'], 'scaffold_shifts', ['id'], OnDelete::SetNull);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldShiftsColumns(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->identity('id')
            ->bigInteger('crew_id')
            ->timestampTz('starts_at');
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldShiftsConstraints(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->primary(['id'])
            ->foreignKey('crew_id', 'scaffold_crews', 'id', OnDelete::Cascade);
    }
}
