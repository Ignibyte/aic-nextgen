<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

/**
 * What `ledger.tally` answers (#333).
 */
final readonly class LedgerTally
{
    /**
     * @param int $total The amounts' sum, in cents.
     * @param int $count How many amounts it added.
     */
    public function __construct(
        public int $total,
        public int $count,
    ) {
    }
}
