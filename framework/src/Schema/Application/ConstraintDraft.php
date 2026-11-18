<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Domain\Check;
use AIC\Schema\Domain\ColumnGroup;
use AIC\Schema\Domain\Constraints;
use AIC\Schema\Domain\ForeignKey;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\MethodIndex;
use AIC\Schema\Domain\PartialIndex;
use InvalidArgumentException;

/**
 * Collects the unique constraints, indexes, foreign keys and checks a builder
 * is given, for a new table or an existing one.
 */
final class ConstraintDraft
{
    /**
     * The unique constraints, ColumnGroups.
     *
     * @var list<object>
     */
    private array $uniques = [];

    /**
     * The indexes, ColumnGroups.
     *
     * @var list<object>
     */
    private array $indexes = [];

    /**
     * The ForeignKeys.
     *
     * @var list<object>
     */
    private array $foreignKeys = [];

    /**
     * The Checks (#310).
     *
     * @var list<object>
     */
    private array $checks = [];

    /**
     * The PartialIndexes, unique or not (#310).
     *
     * @var list<object>
     */
    private array $partialIndexes = [];

    /**
     * The MethodIndexes (#310).
     *
     * @var list<object>
     */
    private array $methodIndexes = [];

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share.
     *
     * @throws InvalidArgumentException When the list is empty.
     */
    public function unique(array $columns): void
    {
        $this->uniques[] = new ColumnGroup($columns);
    }

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @throws InvalidArgumentException When the list is empty.
     */
    public function index(array $columns): void
    {
        $this->indexes[] = new ColumnGroup($columns);
    }

    /**
     * @param string   $column        The referring column.
     * @param string   $table         The table it refers to.
     * @param string   $foreignColumn The column it refers to there.
     * @param OnDelete $onDelete      What deleting the row it refers to does here.
     *
     * @throws InvalidArgumentException When a name is not an identifier.
     */
    public function foreignKey(string $column, string $table, string $foreignColumn, OnDelete $onDelete): void
    {
        $this->foreignKeys[] = new ForeignKey(new Identifier($column), new Identifier($table), new Identifier($foreignColumn), $onDelete);
    }

    /**
     * @param string    $name      The check's name.
     * @param Predicate $predicate What every row holds.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     */
    public function check(string $name, Predicate $predicate): void
    {
        $this->checks[] = new Check(new Identifier($name), $predicate);
    }

    /**
     * @param list<string> $columns   The columns to index, in order.
     * @param Predicate    $predicate The rows it covers.
     * @param bool         $unique    Whether no two covered rows share the columns' values.
     *
     * @throws InvalidArgumentException When the list is empty.
     */
    public function partialIndex(array $columns, Predicate $predicate, bool $unique): void
    {
        $this->partialIndexes[] = new PartialIndex(new ColumnGroup($columns), $predicate, $unique);
    }

    /**
     * @param list<string> $columns     The columns to index.
     * @param IndexMethod  $indexMethod How.
     *
     * @throws InvalidArgumentException When the list is empty.
     */
    public function methodIndex(array $columns, IndexMethod $indexMethod): void
    {
        $this->methodIndexes[] = new MethodIndex(new ColumnGroup($columns), $indexMethod);
    }

    /**
     * @return Constraints What was collected.
     */
    public function constraints(): Constraints
    {
        return new Constraints(
            $this->uniques,
            $this->indexes,
            $this->foreignKeys,
            $this->checks,
            $this->partialIndexes,
            $this->methodIndexes,
        );
    }
}
