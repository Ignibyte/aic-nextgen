<?php

declare(strict_types=1);

namespace App\Note\Entity;

use function implode;

/**
 * What the stock items a list asks for add up to (#184).
 */
final readonly class StockTotals
{
    /**
     * @param int          $count   How many items match.
     * @param string       $worth   Their prices added up, a decimal string.
     * @param string|null  $dearest The highest of their prices; null when none match.
     * @param list<string> $skus    The SKUs among them, in order.
     */
    public function __construct(
        public int $count,
        public string $worth,
        public ?string $dearest,
        public array $skus,
    ) {
    }

    /**
     * @return string The totals in one line, for a person.
     */
    public function line(): string
    {
        return $this->count . ($this->count === 1 ? ' item' : ' items') . ', $' . $this->worth . ' in all'
            . ($this->dearest === null ? '' : ', the dearest $' . $this->dearest)
            . ($this->skus === [] ? '' : '; SKUs ' . implode(', ', $this->skus)) . '.';
    }
}
