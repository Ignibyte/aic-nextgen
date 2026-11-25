<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * What both builders declare on a table, a new one's and an existing one's
 * alike (#86, #310): unique constraints, indexes and checks. Each method
 * returns the builder it was called on, so a migration keeps chaining.
 */
interface TableConstraints
{
    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share.
     *
     * @return static The builder.
     */
    public function unique(array $columns): static;

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @return static The builder.
     */
    public function index(array $columns): static;

    /**
     * @param string    $name      The check's name; the constraint is `<table>_<name>` (#310).
     * @param Predicate $predicate What every row must hold, from `Schema::expressions()`. A null result passes, as
     *                             in SQL.
     *
     * @return static The builder.
     */
    public function check(string $name, Predicate $predicate): static;

    /**
     * @param list<string> $columns   The columns to index, in order.
     * @param Predicate    $predicate The rows the index covers (#310).
     *
     * @return static The builder.
     */
    public function indexWhere(array $columns, Predicate $predicate): static;

    /**
     * @param list<string> $columns   Columns whose values, taken together, no two covered rows share.
     * @param Predicate    $predicate The rows it covers (#310), such as those not deleted.
     *
     * @return static The builder.
     */
    public function uniqueWhere(array $columns, Predicate $predicate): static;

    /**
     * @param list<string> $columns     The columns to index.
     * @param IndexMethod  $indexMethod How (#310): hash, an inverted index, trigrams of text, JSON paths.
     *
     * @return static The builder.
     */
    public function indexUsing(array $columns, IndexMethod $indexMethod): static;

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share, two nulls counted as the
     *                              same value (#344).
     *
     * @return static The builder.
     */
    public function uniqueWithNulls(array $columns): static;

    /**
     * @param list<string> $columns        The referring columns, in order (#344); one column takes `foreignKey()`.
     * @param string       $table          The table they refer to.
     * @param list<string> $foreignColumns The columns they refer to there, its primary key or a unique constraint's.
     * @param OnDelete     $onDelete       What deleting the row they refer to does here.
     *
     * @return static The builder.
     */
    public function references(array $columns, string $table, array $foreignColumns, OnDelete $onDelete): static;
}
