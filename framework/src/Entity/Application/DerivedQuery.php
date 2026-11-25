<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use InvalidArgumentException;

use function implode;

/**
 * A query over a query's rows (#329), read as a named subquery (`WITH
 * aic_rows`): conditions, a search and order on any column, a computed one
 * included (a rank, a JSON value, a similarity), a page of the rows and their
 * count, and a union with another derived query whose columns align.
 * `EntityQuery::derived()` makes one.
 */
final readonly class DerivedQuery
{
    use QueryPages;

    /** Its conditions, joined by AND. */
    private DerivedConditions $derivedConditions;

    /** Its order and page. */
    private EntityPaging $entityPaging;

    /**
     * @param Database      $database      The database.
     * @param DerivedSource $derivedSource The rows it reads.
     */
    public function __construct(
        private Database $database,
        private DerivedSource $derivedSource,
    ) {
        $this->derivedConditions = new DerivedConditions($derivedSource->derivedColumns);
        $this->entityPaging = new EntityPaging();
    }

    /**
     * @param string               $column     A column of the rows.
     * @param string|int|bool|null $value      What it is compared with; null tests for null.
     * @param Comparison           $comparison How it is compared; Equals by default.
     *
     * @throws InvalidArgumentException When the rows have no such column, null meets an ordering comparison, or the
     *                                  comparison is `Contains` or `Similar`.
     *
     * @return self The query.
     */
    public function where(
        string $column,
        string|int|bool|null $value,
        Comparison $comparison = Comparison::Equals,
    ): self {
        $this->derivedConditions->add(new Condition($column, $value, $comparison));

        return $this;
    }

    /**
     * @param list<string> $columns Columns of the rows any of which may contain the text.
     * @param string       $text    What to find, case-insensitively, as typed; blank matches every row.
     *
     * @throws InvalidArgumentException When no column is given, or the rows lack one.
     *
     * @return self The query.
     */
    public function search(array $columns, string $text): self
    {
        $this->derivedConditions->search($columns, $text);

        return $this;
    }

    /**
     * @param string $column A column of the rows, sorted ascending.
     *
     * @throws InvalidArgumentException When the rows have no such column.
     *
     * @return self The query.
     */
    public function orderBy(string $column): self
    {
        $this->entityPaging->order($this->derivedConditions->column($column));

        return $this;
    }

    /**
     * @param string $column A column of the rows, sorted descending.
     *
     * @throws InvalidArgumentException When the rows have no such column.
     *
     * @return self The query.
     */
    public function orderByDescending(string $column): self
    {
        $this->entityPaging->order($this->derivedConditions->column($column) . ' DESC');

        return $this;
    }

    /**
     * @param self $derivedQuery Another, whose columns have the same names, order and types.
     *
     * @throws InvalidArgumentException When they differ.
     *
     * @return self A query over both's rows, each with its own conditions, order and limit kept.
     */
    public function unionAll(self $derivedQuery): self
    {
        return new self($this->database, $this->source()->union($derivedQuery->source(), 'UNION ALL'));
    }

    /**
     * @param self $derivedQuery Another, whose columns have the same names, order and types.
     *
     * @throws InvalidArgumentException When they differ.
     *
     * @return self A query over both's rows, each distinct row once.
     */
    public function unionDistinct(self $derivedQuery): self
    {
        return new self($this->database, $this->source()->union($derivedQuery->source(), 'UNION'));
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return Rows The matching rows, each with the query's columns.
     */
    public function rows(): Rows
    {
        return $this->database->fetchAll($this->selectSql(), $this->params());
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return int How many rows match; its limit and offset do not apply.
     */
    public function count(): int
    {
        $sql = $this->named() . 'SELECT count(*)::integer AS total FROM aic_rows' . $this->derivedConditions->sql();

        return $this->database->fetchOne($sql, $this->params())->int('total');
    }

    /**
     * @param string $column One of its columns.
     *
     * @throws InvalidArgumentException When the rows have no such column.
     *
     * @return string A SELECT of that column's values, for a subquery.
     */
    public function valuesSql(string $column): string
    {
        return $this->named() . 'SELECT ' . $this->derivedConditions->column($column) . ' FROM aic_rows' . $this->matched();
    }

    /**
     * @return list<string|int> Its values, in the order of their marks.
     */
    public function params(): array
    {
        return [...$this->derivedSource->params, ...$this->derivedConditions->params()];
    }

    /**
     * @return DerivedSource Its own rows as a source.
     */
    private function source(): DerivedSource
    {
        return new DerivedSource($this->selectSql(), $this->params(), $this->derivedSource->derivedColumns);
    }

    /**
     * @return string Its SELECT, its columns in order.
     */
    private function selectSql(): string
    {
        $columns = [];

        foreach ($this->derivedSource->derivedColumns->names() as $name) {
            $columns[] = 'aic_rows.' . $name;
        }

        return $this->named() . 'SELECT ' . implode(', ', $columns) . ' FROM aic_rows' . $this->matched();
    }

    /**
     * @return string The rows it reads, named.
     */
    private function named(): string
    {
        return 'WITH aic_rows AS (' . $this->derivedSource->sql . ') ';
    }

    /**
     * @return string Its WHERE, ORDER BY, LIMIT and OFFSET, each when it has one.
     */
    private function matched(): string
    {
        return $this->derivedConditions->sql() . $this->entityPaging->sql('aic_rows');
    }
}
