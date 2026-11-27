<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\ToolInfo;
use AIC\Mcp\Contracts\McpTool;
use AIC\Mcp\Contracts\ToolRefusedException;

use function array_slice;
use function array_sum;
use function count;

/**
 * The fixture's stepped MCP tool (#333): it adds amounts one step at a time,
 * so a client that sends a progress token hears each running total before the
 * answer. It reads nothing and writes nothing, so it may run dry.
 */
#[ToolInfo(
    name: 'ledger.tally',
    description: 'Add up amounts of money in cents, one at a time, reporting the running total after each.',
    needs: 'ledger.split',
    dryRun: true,
)]
final readonly class LedgerTallyTool implements McpTool
{
    /**
     * @param LedgerTallyInput $ledgerTallyInput What to add up.
     *
     * @return int How many steps: one per amount; none past 100, which the call refuses.
     */
    public function steps(LedgerTallyInput $ledgerTallyInput): int
    {
        $count = count($ledgerTallyInput->amounts);

        return $count > 100
            ? 0
            : $count;
    }

    /**
     * @param LedgerTallyInput $ledgerTallyInput What to add up.
     * @param int              $step             Which amount, from 0.
     *
     * @return string The amount and the total so far.
     */
    public function step(LedgerTallyInput $ledgerTallyInput, int $step): string
    {
        $amount = $ledgerTallyInput->amounts[$step] ?? 0;
        $total = array_sum(array_slice($ledgerTallyInput->amounts, 0, $step + 1));

        return 'Added ' . $amount . ': ' . $total . ' so far.';
    }

    /**
     * @param LedgerTallyInput $ledgerTallyInput What to add up.
     *
     * @throws ToolRefusedException When there are more than 100 amounts.
     *
     * @return LedgerTally The total.
     */
    public function call(LedgerTallyInput $ledgerTallyInput): LedgerTally
    {
        $count = count($ledgerTallyInput->amounts);

        if ($count > 100) {
            throw new ToolRefusedException('Tally at most 100 amounts.');
        }

        return new LedgerTally(array_sum($ledgerTallyInput->amounts), $count);
    }
}
