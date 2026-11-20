<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_loans` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldLoansTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_20_000002_create_scaffold_loans_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_loans');
        $this->scaffoldLoansColumns($tableBuilder, $schema->types());
        $this->scaffoldLoansConstraints($tableBuilder);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param ColumnTypes  $columnTypes  Its column types.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldLoansColumns(TableBuilder $tableBuilder, ColumnTypes $columnTypes): void
    {
        $tableBuilder
            ->identity('id')
            ->column('book_id', $columnTypes->uuid())
            ->bigInteger('task_id')
            ->nullable()
            ->string('borrower', 64)
            ->timestampTz('lent_at')
            ->timestampTz('returned_at')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldLoansConstraints(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->primary(['id'])
            ->unique(['book_id', 'lent_at'])
            ->foreignKey('book_id', 'scaffold_books', 'id', OnDelete::Restrict)
            ->foreignKey('task_id', 'scaffold_tasks', 'id', OnDelete::SetNull)
            ->index(['borrower', 'lent_at'])
            ->indexUsing(['lent_at'], IndexMethod::Brin);
    }
}
