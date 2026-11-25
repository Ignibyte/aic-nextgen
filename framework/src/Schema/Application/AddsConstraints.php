<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Predicate;
use InvalidArgumentException;
use Override;

/**
 * The unique constraints, indexes and checks both builders take, a new
 * table's and an existing table's alike (#86, #310): each goes to the
 * builder's ConstraintDraft.
 */
trait AddsConstraints
{
    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows may share.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function unique(array $columns): static
    {
        $this->constraintDraft->unique($columns);

        return $this;
    }

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function index(array $columns): static
    {
        $this->constraintDraft->index($columns);

        return $this;
    }

    /**
     * @param string    $name      The check's name; the constraint is `<table>_<name>` (#310).
     * @param Predicate $predicate What every row holds.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     *
     * @return static The builder.
     */
    #[Override]
    public function check(string $name, Predicate $predicate): static
    {
        $this->constraintDraft->check($name, $predicate);

        return $this;
    }

    /**
     * @param list<string> $columns   The columns to index, in order.
     * @param Predicate    $predicate The rows the index covers (#310).
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function indexWhere(array $columns, Predicate $predicate): static
    {
        $this->constraintDraft->partialIndex($columns, $predicate, false);

        return $this;
    }

    /**
     * @param list<string> $columns   Columns whose values, taken together, no two covered rows share.
     * @param Predicate    $predicate The rows it covers (#310).
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function uniqueWhere(array $columns, Predicate $predicate): static
    {
        $this->constraintDraft->partialIndex($columns, $predicate, true);

        return $this;
    }

    /**
     * @param list<string> $columns     The columns to index.
     * @param IndexMethod  $indexMethod How (#310).
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function indexUsing(array $columns, IndexMethod $indexMethod): static
    {
        $this->constraintDraft->methodIndex($columns, $indexMethod);

        return $this;
    }

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share, two nulls counted as the
     *                              same value (#344).
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return static The builder.
     */
    #[Override]
    public function uniqueWithNulls(array $columns): static
    {
        $this->constraintDraft->unique($columns, true);

        return $this;
    }

    /**
     * @param list<string> $columns        The referring columns, in order (#344); one column takes `foreignKey()`.
     * @param string       $table          The table they refer to.
     * @param list<string> $foreignColumns The columns they refer to there, its primary key or a unique constraint's.
     * @param OnDelete     $onDelete       What deleting the row they refer to does here.
     *
     * @throws InvalidArgumentException When a name is not an identifier, there are no columns, or the two lists differ
     *                                  in length.
     *
     * @return static The builder.
     */
    #[Override]
    public function references(array $columns, string $table, array $foreignColumns, OnDelete $onDelete): static
    {
        $this->constraintDraft->foreignKey($columns, $table, $foreignColumns, $onDelete);

        return $this;
    }
}
