<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use InvalidArgumentException;

/**
 * The page an entity query and a derived query (#329) take the same way: how
 * many rows they return and skip.
 */
trait QueryPages
{
    /**
     * @param int $count How many rows it returns at most.
     *
     * @throws InvalidArgumentException When the count is below 1.
     *
     * @return self The query.
     */
    public function limit(int $count): self
    {
        $this->entityPaging->limit($count);

        return $this;
    }

    /**
     * @param int $count How many matching rows it skips, in its order.
     *
     * @throws InvalidArgumentException When the count is negative.
     *
     * @return self The query.
     */
    public function offset(int $count): self
    {
        $this->entityPaging->offset($count);

        return $this;
    }
}
