<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Arithmetic;
use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Operator;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Contracts\Term;
use InvalidArgumentException;
use Override;

/**
 * The `scaffold_books` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldBooksTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_20_000001_create_scaffold_books_table';
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
        $tableBuilder = $schema->create('scaffold_books');
        $this->scaffoldBooksColumns($tableBuilder, $schema->types(), $schema->expressions());
        $this->scaffoldBooksConstraints($tableBuilder, $schema->expressions());
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param ColumnTypes  $columnTypes  Its column types.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldBooksColumns(
        TableBuilder $tableBuilder,
        ColumnTypes $columnTypes,
        Expressions $expressions,
    ): void {
        $tableBuilder
            ->column('id', $columnTypes->uuidKey())
            ->bigInteger('shelf_id')
            ->text('title')
            ->string('isbn', 13)
            ->nullable()
            ->integer('pages')
            ->numeric('price', 8, 2)
            ->column('tags', $columnTypes->jsonb())
            ->column('retired', $columnTypes->defaulted($columnTypes->boolean(), $expressions->boolean(false)))
            ->date('published_on')
            ->nullable()
            ->column('label', $columnTypes->generated($columnTypes->text(), $this->labelTerm($expressions)))
            ->column('worth', $columnTypes->generated($columnTypes->numeric(12, 2), $this->worthTerm($expressions)));
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldBooksConstraints(TableBuilder $tableBuilder, Expressions $expressions): void
    {
        $tableBuilder
            ->primary(['id'])
            ->foreignKey('shelf_id', 'scaffold_shelves', 'id', OnDelete::Cascade)
            ->check('isbn_shape', $this->isbnShapeCheck($expressions))
            ->check('pages_bounded', $this->pagesBoundedCheck($expressions))
            ->uniqueWhere(['isbn'], $expressions->not($expressions->isNull($expressions->column('isbn'))))
            ->indexWhere(['shelf_id'], $this->shelfIdIndexRows($expressions))
            ->indexUsing(['tags'], IndexMethod::JsonPath)
            ->indexUsing(['title'], IndexMethod::Trigram);
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Term The generated `label`.
     */
    private function labelTerm(Expressions $expressions): Term
    {
        return $expressions->concat([$expressions->lower($expressions->column('title')), $expressions->text("'s copy")]);
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Term The generated `worth`.
     */
    private function worthTerm(Expressions $expressions): Term
    {
        return $expressions->calculate($expressions->column('price'), Arithmetic::Times, $expressions->decimal('2'));
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `isbn_shape` check.
     */
    private function isbnShapeCheck(Expressions $expressions): Predicate
    {
        return $expressions->any([
            $expressions->isNull($expressions->column('isbn')),
            $expressions->compare($expressions->length($expressions->column('isbn')), Operator::Equal, $expressions->integer(13)),
        ]);
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `pages_bounded` check.
     */
    private function pagesBoundedCheck(Expressions $expressions): Predicate
    {
        return $expressions->all([
            $expressions->compare($expressions->column('pages'), Operator::Greater, $expressions->integer(0)),
            $expressions->compare($expressions->column('pages'), Operator::Less, $expressions->integer(10_000)),
        ]);
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The rows the index on shelf_id covers.
     */
    private function shelfIdIndexRows(Expressions $expressions): Predicate
    {
        return $expressions->compare($expressions->column('retired'), Operator::Equal, $expressions->boolean(false));
    }
}
