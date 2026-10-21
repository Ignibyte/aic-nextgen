<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Retype;
use LogicException;

use function array_pop;

/**
 * What an AlterationDraft has collected for an existing table's columns: the
 * columns to add and the type changes, each in order (#86).
 */
final class ColumnChanges
{
    /**
     * The columns to add, in order.
     *
     * @var list<object>
     */
    private array $added = [];

    /**
     * The type changes, in order.
     *
     * @var list<object>
     */
    private array $retyped = [];

    /**
     * @param Column $column A column to add.
     */
    public function add(Column $column): void
    {
        $this->added[] = $column;
    }

    /**
     * Lets the last column added hold null.
     *
     * @throws LogicException When no column has been added.
     */
    public function nullableLast(): void
    {
        $last = array_pop($this->added);

        if (!$last instanceof Column) {
            throw new LogicException('nullable() follows addColumn().');
        }

        $this->added[] = $last->asNullable();
    }

    /**
     * @param Retype $retype A column's new type.
     */
    public function retype(Retype $retype): void
    {
        $this->retyped[] = $retype;
    }

    /**
     * @return list<object> The columns to add, Columns in order.
     */
    public function added(): array
    {
        return $this->added;
    }

    /**
     * @return list<object> The type changes, Retypes in order.
     */
    public function retyped(): array
    {
        return $this->retyped;
    }
}
