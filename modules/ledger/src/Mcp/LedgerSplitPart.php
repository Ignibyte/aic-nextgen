<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

/**
 * One part of a split amount (#316).
 */
final readonly class LedgerSplitPart
{
    /**
     * @param int    $index Its place, from 0.
     * @param int    $cents Its share, in cents.
     * @param string $label Its label.
     */
    public function __construct(
        public int $index,
        public int $cents,
        public string $label,
    ) {
    }
}
