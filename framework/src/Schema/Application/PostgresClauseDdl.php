<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Domain\Check;
use AIC\Schema\Domain\ColumnGroup;
use AIC\Schema\Domain\Constraints;
use AIC\Schema\Domain\ForeignKey;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;

use function array_merge;
use function implode;

/**
 * Postgres table constraints, as `CREATE TABLE` lists them and `ALTER TABLE
 * ... ADD` adds them: unique constraints, foreign keys and checks (#310).
 */
final readonly class PostgresClauseDdl
{
    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When a check's name would be longer than an identifier may be.
     *
     * @return list<string> Its unique constraints, its foreign keys and then its checks, as table constraints.
     */
    public function clauses(Identifier $identifier, Constraints $constraints): array
    {
        $checks = [];

        foreach ($constraints->checks as $check) {
            if (!$check instanceof Check) {
                continue;
            }

            $checks[] = 'CONSTRAINT ' . $check->constraintName($identifier)->value . ' CHECK ' . $check->predicate->sql;
        }

        return array_merge($this->uniques($constraints), $this->foreignKeys($constraints), $checks);
    }

    /**
     * @param Constraints $constraints A table's constraints.
     *
     * @return list<string> Its unique constraints.
     */
    private function uniques(Constraints $constraints): array
    {
        $definitions = [];

        foreach ($constraints->uniques as $unique) {
            if (!($unique instanceof ColumnGroup)) {
                continue;
            }

            $nulls = $unique->nullsEqual
                ? 'NULLS NOT DISTINCT '
                : '';
            $definitions[] = 'UNIQUE ' . $nulls . '(' . implode(', ', $unique->columns) . ')';
        }

        return $definitions;
    }

    /**
     * @param Constraints $constraints A table's constraints.
     *
     * @return list<string> Its foreign-key constraints.
     */
    private function foreignKeys(Constraints $constraints): array
    {
        $definitions = [];

        foreach ($constraints->foreignKeys as $foreignKey) {
            if (!($foreignKey instanceof ForeignKey)) {
                continue;
            }

            $definitions[] = $this->foreignKey($foreignKey);
        }

        return $definitions;
    }

    /**
     * @param ForeignKey $foreignKey A foreign key.
     *
     * @return string Its table constraint.
     */
    private function foreignKey(ForeignKey $foreignKey): string
    {
        $onDelete = match ($foreignKey->onDelete) {
            OnDelete::Restrict => 'RESTRICT',
            OnDelete::Cascade => 'CASCADE',
            OnDelete::SetNull => 'SET NULL',
        };

        return 'FOREIGN KEY (' . implode(', ', $foreignKey->columns) . ') REFERENCES ' . $foreignKey->table->value
            . ' (' . implode(', ', $foreignKey->foreignColumns) . ') ON DELETE ' . $onDelete;
    }
}
