<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Domain\ColumnGroup;
use AIC\Schema\Domain\Constraints;
use AIC\Schema\Domain\ForeignKey;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;

/**
 * Collects the unique constraints, indexes and foreign keys a builder is
 * given, for a new table or an existing one.
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
     * @return Constraints What was collected.
     */
    public function constraints(): Constraints
    {
        return new Constraints($this->uniques, $this->indexes, $this->foreignKeys);
    }
}
