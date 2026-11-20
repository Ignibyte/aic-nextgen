<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Operator;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_shelves` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldShelvesTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_20_000000_create_scaffold_shelves_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_shelves');
        $this->scaffoldShelvesColumns($tableBuilder, $schema->types(), $schema->expressions());
        $this->scaffoldShelvesConstraints($tableBuilder, $schema->expressions());
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param ColumnTypes  $columnTypes  Its column types.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldShelvesColumns(
        TableBuilder $tableBuilder,
        ColumnTypes $columnTypes,
        Expressions $expressions,
    ): void {
        $tableBuilder
            ->identity('id')
            ->string('name', 100)
            ->column('zone', $columnTypes->defaulted($columnTypes->choice(['front', 'back']), $expressions->text('front')))
            ->integer('capacity')
            ->timestampTz('created_at');
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldShelvesConstraints(TableBuilder $tableBuilder, Expressions $expressions): void
    {
        $tableBuilder
            ->primary(['id'])
            ->unique(['name'])
            ->check('capacity_positive', $this->capacityPositiveCheck($expressions));
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `capacity_positive` check.
     */
    private function capacityPositiveCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare($expressions->column('capacity'), Operator::Greater, $expressions->integer(0));
    }
}
