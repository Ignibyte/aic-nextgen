<?php

declare(strict_types=1);

namespace App\Stock;

/**
 * One stock item as read back from the database.
 */
final readonly class StockItem
{
    /**
     * @param string $name      What it is.
     * @param string $price     Its price, a decimal string with cents.
     * @param string $addedOn   The date it came in, `YYYY-MM-DD`.
     * @param bool   $certified Whether it is certified.
     */
    public function __construct(
        public string $name,
        public string $price,
        public string $addedOn,
        public bool $certified,
    ) {
    }

    /**
     * @return string It in one line, for a person.
     */
    public function line(): string
    {
        return $this->name . ': $' . $this->price . ', in stock since ' . $this->addedOn . ', '
            . ($this->certified ? 'certified' : 'not certified');
    }
}
