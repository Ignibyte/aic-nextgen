<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use InvalidArgumentException;

use function implode;

/**
 * A query's order, page and lock (#184, moved out of `EntityQuery` by #306):
 * the columns it sorts by, how many rows it skips and returns, and whether it
 * locks what it reads.
 */
final class EntityPaging
{
    /**
     * The columns to sort by, each ascending or with `DESC`.
     *
     * @var list<string>
     */
    private array $order = [];

    /** How many rows it returns at most; 0 for no limit. */
    private int $limit = 0;

    /** How many matching rows it skips. */
    private int $offset = 0;

    /** Whether it locks the rows it reads. */
    private bool $locked = false;

    /**
     * @param string $column A qualified column, with ` DESC` to sort it descending.
     */
    public function order(string $column): void
    {
        $this->order[] = $column;
    }

    /**
     * @param int $count How many rows it returns at most.
     *
     * @throws InvalidArgumentException When the count is below 1.
     */
    public function limit(int $count): void
    {
        $this->limit = $count >= 1
            ? $count
            : throw new InvalidArgumentException('A query limit must be at least 1, not ' . $count . '.');
    }

    /**
     * @param int $count How many matching rows it skips, in its order.
     *
     * @throws InvalidArgumentException When the count is negative.
     */
    public function offset(int $count): void
    {
        $this->offset = $count >= 0
            ? $count
            : throw new InvalidArgumentException('A query offset cannot be negative, not ' . $count . '.');
    }

    /**
     * Locks the rows it reads until the transaction ends.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * @param string $table The queried table, the one a lock names.
     *
     * @return string Its `ORDER BY`, `LIMIT`, `OFFSET` and `FOR UPDATE OF`, each when it has one.
     */
    public function sql(string $table): string
    {
        return ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order))
            . ($this->limit > 0 ? ' LIMIT ' . $this->limit : '')
            . ($this->offset > 0 ? ' OFFSET ' . $this->offset : '')
            . ($this->locked ? ' FOR UPDATE OF ' . $table : '');
    }
}
