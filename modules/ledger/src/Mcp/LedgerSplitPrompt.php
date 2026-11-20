<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;
use AIC\Mcp\Contracts\McpPrompt;
use Override;

use function sprintf;

/**
 * The fixture's MCP prompt (#316): it asks the agent to split a bill with
 * `ledger.split` and say who pays what.
 */
#[PromptInfo(
    name: 'ledger.split-bill',
    description: 'Ask to split a bill between people and say who pays what.',
    needs: 'ledger.mcp',
    arguments: ['amount' => 'The bill, in cents.', 'people' => 'Who shares it, comma-separated.'],
    optional: ['people'],
)]
final readonly class LedgerSplitPrompt implements McpPrompt
{
    /**
     * @param McpArguments $mcpArguments The amount and, if given, the people.
     *
     * @return list<McpMessage> One message from the user.
     */
    #[Override]
    public function messages(McpArguments $mcpArguments): array
    {
        $people = $mcpArguments->has('people')
            ? $mcpArguments->string('people')
            : 'two people';

        return [new McpMessage(sprintf(
            'Split a bill of %s cents between %s with the ledger.split tool, then say who pays what.',
            $mcpArguments->string('amount'),
            $people,
        ))];
    }
}
