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
 * The `scaffold_permits` table, made by bin/aic schema:import (#318) from a pg_dump.
 */
final readonly class CreateScaffoldPermitsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_25_000001_create_scaffold_permits_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $tableBuilder = $schema->create('scaffold_permits');
        $this->scaffoldPermitsColumnsPart1($tableBuilder, $schema->types());
        $this->scaffoldPermitsColumnsPart2($tableBuilder);
        $this->scaffoldPermitsConstraints($tableBuilder, $schema->expressions());
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param ColumnTypes  $columnTypes  Its column types.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldPermitsColumnsPart1(TableBuilder $tableBuilder, ColumnTypes $columnTypes): void
    {
        $tableBuilder
            ->identity('id')
            ->string('code', 8)
            ->text('zone')
            ->text('holder')
            ->nullable()
            ->numeric('latitude', 30, 15)
            ->nullable()
            ->numeric('fee', 8, 2)
            ->column('areas', $columnTypes->jsonb())
            ->timestampTz('closed_at')
            ->nullable()
            ->text('closed_by')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldPermitsColumnsPart2(TableBuilder $tableBuilder): void
    {
        $tableBuilder
            ->text('contact_email')
            ->nullable()
            ->text('contact_phone')
            ->nullable()
            ->text('decision')
            ->nullable();
    }

    /**
     * @param TableBuilder $tableBuilder The table.
     * @param Expressions  $expressions  Its terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    private function scaffoldPermitsConstraints(TableBuilder $tableBuilder, Expressions $expressions): void
    {
        $tableBuilder
            ->primary(['id'])
            ->unique(['id', 'zone'])
            ->check('closed_whole', $this->closedWholeCheck($expressions))
            ->check('code_shape', $expressions->matches($expressions->column('code'), '^[A-Z]{2}[0-9]{6}$'))
            ->check('areas_listed', $this->areasListedCheck($expressions))
            ->check('contact_given', $this->contactGivenCheck($expressions))
            ->check('decided', $this->decidedCheck($expressions))
            ->check('fee_in_cents', $this->feeInCentsCheck($expressions))
            ->check('fee_known', $this->feeKnownCheck($expressions))
            ->check('holder_present', $this->holderPresentCheck($expressions));
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `closed_whole` check.
     */
    private function closedWholeCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare(
            $expressions->truth($expressions->isNull($expressions->column('closed_at'))),
            Operator::Equal,
            $expressions->truth($expressions->isNull($expressions->column('closed_by'))),
        );
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `areas_listed` check.
     */
    private function areasListedCheck(Expressions $expressions): Predicate
    {
        return $expressions->all([
            $expressions->compare($expressions->jsonKind($expressions->column('areas')), Operator::Equal, $expressions->text('array')),
            $expressions->compare(
                $expressions->jsonLength($expressions->column('areas')),
                Operator::GreaterOrEqual,
                $expressions->integer(1),
            ),
        ]);
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `contact_given` check.
     */
    private function contactGivenCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare(
            $expressions->countPresent([$expressions->column('contact_email'), $expressions->column('contact_phone')]),
            Operator::GreaterOrEqual,
            $expressions->integer(1),
        );
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `decided` check.
     */
    private function decidedCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare(
            $expressions->truth($expressions->not($expressions->isNull($expressions->column('decision')))),
            Operator::Equal,
            $expressions->coalesce([
                $expressions->truth($expressions->compare($expressions->column('zone'), Operator::Equal, $expressions->text('restricted'))),
                $expressions->boolean(false),
            ]),
        );
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `fee_in_cents` check.
     */
    private function feeInCentsCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare($expressions->column('fee'), Operator::Equal, $expressions->round($expressions->column('fee'), 2));
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `fee_known` check.
     */
    private function feeKnownCheck(Expressions $expressions): Predicate
    {
        return $expressions->compare(
            $expressions->coalesce([
                $expressions->truth(
                    $expressions->compare($expressions->column('fee'), Operator::GreaterOrEqual, $expressions->decimal('0')),
                ),
                $expressions->boolean(false),
            ]),
            Operator::Equal,
            $expressions->boolean(true),
        );
    }

    /**
     * @param Expressions $expressions The table's terms and predicates.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     *
     * @return Predicate The `holder_present` check.
     */
    private function holderPresentCheck(Expressions $expressions): Predicate
    {
        return $expressions->any([
            $expressions->isNull($expressions->column('holder')),
            $expressions->compare($expressions->trim($expressions->column('holder')), Operator::NotEqual, $expressions->text('')),
        ]);
    }
}
