<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use App\Ledger\Enum\LedgerCurrency;

/**
 * What `ledger.split` takes (#316). The build writes the tool's input schema
 * from this constructor: each parameter's type, its default and its
 * description.
 */
final readonly class LedgerSplitInput
{
    /**
     * @param int            $amount   The amount to split, in cents.
     * @param int            $parts    How many parts to split it into, 1 to 100.
     * @param LedgerCurrency $currency The amount's currency.
     * @param list<string>   $labels   A label for each part, in order; a part with none is "part N".
     * @param string|null    $memo     A note the result repeats.
     */
    public function __construct(
        public int $amount,
        public int $parts = 2,
        public LedgerCurrency $currency = LedgerCurrency::Usd,
        public array $labels = [],
        public ?string $memo = null,
    ) {
    }
}
