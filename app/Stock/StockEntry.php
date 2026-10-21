<?php

declare(strict_types=1);

namespace App\Stock;

use function checkdate;
use function preg_match;
use function strlen;
use function substr;

/**
 * A stock item as posted, before it is stored, and what is wrong with it.
 */
final readonly class StockEntry
{
    /**
     * @param string $name      What it is, 1 to 100 bytes.
     * @param string $price     Its price: whole dollars, or dollars and cents.
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
     * @return string What is wrong with it, for a person; empty when nothing is.
     */
    public function problem(): string
    {
        return match (true) {
            $this->name === '' || strlen($this->name) > 100 => 'Give the item a name of up to 100 characters.',
            preg_match('/^\\d{1,10}(\\.\\d{1,2})?$/', $this->price) !== 1 => 'Give the price in dollars, such as 18999 or 18999.99.',
            !$this->isDate() => 'Give the date as YYYY-MM-DD, a real day.',
            default => '',
        };
    }

    /**
     * @return bool Whether the date is a real calendar day written as `YYYY-MM-DD`.
     */
    private function isDate(): bool
    {
        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $this->addedOn) === 1
            && checkdate((int) substr($this->addedOn, 5, 2), (int) substr($this->addedOn, 8, 2), (int) substr($this->addedOn, 0, 4));
    }
}
