<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Arithmetic;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Operator;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `ledger_entries` table, made by bin/aic make:migration (#122): an
 * append-only ledger (#310). Its total and label are generated columns, its
 * quantity, price and account are checked, its memo has a trigram index and
 * its stamp a block-range index, and every update, delete and truncate is
 * refused by the database.
 */
final readonly class CreateLedgerEntriesTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_042955_create_ledger_entries_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->extension('pg_trgm');
        $tableBuilder = $this->columns($schema);
        $this->checks($tableBuilder, $schema->expressions());
        $tableBuilder->indexUsing(['memo'], IndexMethod::Trigram)->indexUsing(['created_at'], IndexMethod::Brin);
        $schema->appendOnly('ledger_entries');
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     *
     * @return TableBuilder The table with its columns: the total is quantity times price, the label the account in
     *                      lower case, `'s ` and the memo.
     */
    private function columns(Schema $schema): TableBuilder
    {
        $expressions = $schema->expressions();
        $columnTypes = $schema->types();
        $term = $expressions->calculate($expressions->column('quantity'), Arithmetic::Times, $expressions->column('unit_price'));
        $label = $expressions->concat([
            $expressions->lower($expressions->column('account')),
            $expressions->text("'s "),
            $expressions->column('memo'),
        ]);

        return $schema->create('ledger_entries')
            ->identity('id')
            ->text('account')
            ->integer('quantity')
            ->numeric('unit_price', 10, 2)
            ->text('memo')
            ->column('total', $columnTypes->generated($columnTypes->numeric(12, 2), $term))
            ->column('label', $columnTypes->generated($columnTypes->text(), $label))
            ->timestampTz('created_at')
            ->primary(['id']);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a check is declared wrongly.
     */
    private function checks(TableBuilder $tableBuilder, Expressions $expressions): void
    {
        $term = $expressions->column('unit_price');
        $tableBuilder
            ->check('quantity_positive', $expressions->compare(
                $expressions->column('quantity'),
                Operator::Greater,
                $expressions->integer(0),
            ))
            ->check('price_bounded', $expressions->all([
                $expressions->compare($term, Operator::GreaterOrEqual, $expressions->decimal('0.00')),
                $expressions->compare($term, Operator::Less, $expressions->decimal('100000')),
            ]))
            ->check('account_named', $expressions->compare(
                $expressions->length($expressions->column('account')),
                Operator::GreaterOrEqual,
                $expressions->integer(1),
            ));
    }
}
