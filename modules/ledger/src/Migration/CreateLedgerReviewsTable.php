<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Operator;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use InvalidArgumentException;
use Override;

/**
 * The `ledger_reviews` table, made by bin/aic make:migration (#122): a
 * review of a ledger entry (#310). Its decision and reviewer are set
 * together, once: the database refuses changing either after, or deleting a
 * decided review. An entry has one open review at most, and escalated ones
 * have an index of their own.
 */
final readonly class CreateLedgerReviewsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_042956_create_ledger_reviews_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $expressions = $schema->expressions();
        $predicate = $expressions->isNull($expressions->column('decision'));
        $tableBuilder = $schema->create('ledger_reviews')
            ->identity('id')
            ->bigInteger('entry_id')
            ->column('decision', $schema->types()->choice(['approved', 'rejected']))
            ->nullable()
            ->text('reviewer')
            ->nullable()
            ->boolean('escalated')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->foreignKey('entry_id', 'ledger_entries', 'id', OnDelete::Restrict);
        $this->checks($this->indexes($tableBuilder, $expressions, $predicate), $expressions, $predicate);
        $schema->finalOnce('ledger_reviews', ['decision', 'reviewer']);
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     * @param Predicate    $predicate    Whether a review has no decision yet.
     *
     * @throws InvalidArgumentException When an index is declared wrongly.
     *
     * @return TableBuilder The table, with one open review per entry at most and an index of the escalated ones.
     */
    private function indexes(TableBuilder $tableBuilder, Expressions $expressions, Predicate $predicate): TableBuilder
    {
        return $tableBuilder
            ->uniqueWhere(['entry_id'], $predicate)
            ->indexWhere(['entry_id'], $expressions->compare(
                $expressions->column('escalated'),
                Operator::Equal,
                $expressions->boolean(true),
            ));
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     * @param Predicate    $predicate    Whether a review has no decision yet.
     *
     * @throws InvalidArgumentException When a check is declared wrongly.
     */
    private function checks(TableBuilder $tableBuilder, Expressions $expressions, Predicate $predicate): void
    {
        $unsigned = $expressions->isNull($expressions->column('reviewer'));
        $known = $expressions->oneOf($expressions->lower($expressions->column('reviewer')), [
            $expressions->text('ada'),
            $expressions->text('grace'),
        ]);
        $tableBuilder
            ->check('decided_whole', $expressions->any([
                $expressions->all([$predicate, $unsigned]),
                $expressions->not($expressions->any([$predicate, $unsigned])),
            ]))
            ->check('reviewer_known', $expressions->any([$unsigned, $known]));
    }
}
