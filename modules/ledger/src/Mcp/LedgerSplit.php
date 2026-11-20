<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use App\Ledger\Enum\LedgerCurrency;

/**
 * What `ledger.split` answers (#316). The build writes the tool's output schema
 * and its JSON from this constructor.
 */
final readonly class LedgerSplit
{
    /**
     * @param list<LedgerSplitPart> $parts    The parts, in order; the first ones take the remainder.
     * @param LedgerCurrency        $currency Their currency.
     * @param bool                  $exact    Whether every part is the same.
     * @param string|null           $memo     The memo the call gave.
     */
    public function __construct(
        public array $parts,
        public LedgerCurrency $currency,
        public bool $exact,
        public ?string $memo,
    ) {
    }
}
