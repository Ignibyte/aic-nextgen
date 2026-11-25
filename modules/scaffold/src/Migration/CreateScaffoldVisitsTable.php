<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Defaults;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_visits` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldVisitsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_25_000000_create_scaffold_visits_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_visits');
        $this->scaffoldVisitsColumns($tableBuilder, $schema->types(), $schema->defaults(), $schema->expressions());
        $this->scaffoldVisitsConstraints($tableBuilder);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param ColumnTypes  $columnTypes  Its column types.
     * @param Defaults     $defaults     Its common defaults.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldVisitsColumns(
        TableBuilder $tableBuilder,
        ColumnTypes $columnTypes,
        Defaults $defaults,
        Expressions $expressions,
    ): void {
        $tableBuilder
            ->identity('id')
            ->string('path', 255)
            ->column('hits', $columnTypes->defaulted($columnTypes->integer(), $expressions->integer(0)))
            ->column('tags', $columnTypes->defaulted($columnTypes->jsonb(), $defaults->json('[]')))
            ->column('payload', $columnTypes->jsonb())
            ->nullable()
            ->column('seen_at', $columnTypes->timestampTz(0))
            ->nullable()
            ->column('created_at', $columnTypes->defaulted($columnTypes->timestampTz(0), $defaults->now()))
            ->column('updated_at', $columnTypes->defaulted($columnTypes->timestampTz(), $defaults->now()))
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldVisitsConstraints(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->primary(['id']);
    }
}
