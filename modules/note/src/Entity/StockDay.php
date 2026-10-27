<?php

declare(strict_types=1);

namespace App\Note\Entity;

/**
 * One day of the stock report (#191), read from the `stock_days` view.
 */
final readonly class StockDay
{
    /**
     * @param string $addedOn The day, `YYYY-MM-DD`.
     * @param int    $items   How many items came in that day.
     * @param string $worth   What they are worth, a decimal string.
     */
    public function __construct(
        public string $addedOn,
        public int $items,
        public string $worth,
    ) {
    }

    /**
     * @return string It in one line, for a person.
     */
    public function line(): string
    {
        return $this->addedOn . ': ' . $this->items . ($this->items === 1 ? ' item' : ' items') . ', $' . $this->worth;
    }
}
