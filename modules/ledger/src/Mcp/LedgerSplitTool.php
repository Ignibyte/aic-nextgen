<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\ToolInfo;
use AIC\Mcp\Contracts\McpTool;
use AIC\Mcp\Contracts\ToolRefusedException;

use function intdiv;

/**
 * The fixture's MCP tool (#316): it splits an amount into equal parts in whole
 * cents. It reads nothing and writes nothing, so gate:23's two passes over one
 * database answer alike.
 */
#[ToolInfo(
    name: 'ledger.split',
    description: 'Split an amount of money into equal parts in whole cents; the first parts take the remainder.',
    needs: 'ledger.split',
)]
final readonly class LedgerSplitTool implements McpTool
{
    /**
     * @param LedgerSplitInput $ledgerSplitInput What to split.
     *
     * @throws ToolRefusedException When the amount is negative or the parts are not 1 to 100.
     *
     * @return LedgerSplit The parts.
     */
    public function call(LedgerSplitInput $ledgerSplitInput): LedgerSplit
    {
        $amount = $ledgerSplitInput->amount;
        $count = $ledgerSplitInput->parts;

        if ($amount < 0 || $count < 1 || $count > 100) {
            throw new ToolRefusedException('Split an amount of 0 cents or more into 1 to 100 parts.');
        }

        $share = intdiv($amount, $count);
        $remainder = $amount % $count;
        $parts = [];

        for ($index = 0; $index < $count; $index += 1) {
            $label = $ledgerSplitInput->labels[$index] ?? 'part ' . ($index + 1);
            $parts[] = new LedgerSplitPart($index, $share + ($index < $remainder ? 1 : 0), $label);
        }

        return new LedgerSplit($parts, $ledgerSplitInput->currency, $remainder === 0, $ledgerSplitInput->memo);
    }
}
