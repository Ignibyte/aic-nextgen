<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use InvalidArgumentException;
use Override;

/**
 * The unique constraints and indexes both builders take, a new table's and an
 * existing table's alike (#86): each goes to the builder's ConstraintDraft.
 */
trait AddsConstraints
{
    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows may share.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function unique(array $columns): self
    {
        $this->constraintDraft->unique($columns);

        return $this;
    }

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function index(array $columns): self
    {
        $this->constraintDraft->index($columns);

        return $this;
    }
}
