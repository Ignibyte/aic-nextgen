<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;

/**
 * The related rows of a page of owner rows (#306), by each owner's key as
 * text: `rowsOf($key)` loops like a query's rows, in the compiled site too.
 */
final readonly class RelatedRows
{
    /**
     * @param array<string, list<Row>> $groups Each owner's related rows, by its key as text.
     */
    public function __construct(
        private array $groups,
    ) {
    }

    /**
     * @param string|int $key An owner row's key.
     *
     * @return Rows Its related rows, in the target's key order; none when it has none.
     */
    public function rowsOf(string|int $key): Rows
    {
        return new Rows($this->groups[(string) $key] ?? []);
    }

    /**
     * @param string|int $key An owner row's key.
     *
     * @return Row|null Its first related row: a HasOne's or a Polymorphic's one; null when it has none.
     */
    public function one(string|int $key): ?Row
    {
        return $this->rowsOf($key)->first();
    }
}
