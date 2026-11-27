<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

/**
 * What `ledger.tally` takes (#333).
 */
final readonly class LedgerTallyInput
{
    /**
     * @param list<int> $amounts The amounts to add up, in cents, in order.
     */
    public function __construct(
        public array $amounts,
    ) {
    }
}
