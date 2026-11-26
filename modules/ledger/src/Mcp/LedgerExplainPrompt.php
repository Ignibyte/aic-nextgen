<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;
use AIC\Mcp\Contracts\McpPrompt;
use Override;

/**
 * A fixture MCP prompt (#334): it asks the agent to explain one account's
 * standing from the ledger's standing resource.
 */
#[PromptInfo(
    name: 'ledger.explain',
    description: 'Ask why an account stands as it does and what may move it next.',
    needs: 'ledger.mcp',
    arguments: ['account' => "The account's name."],
)]
final readonly class LedgerExplainPrompt implements McpPrompt
{
    /**
     * @param McpArguments $mcpArguments The account.
     *
     * @return list<McpMessage> One message from the user.
     */
    #[Override]
    public function messages(McpArguments $mcpArguments): array
    {
        return [new McpMessage(
            'Read ledger://standing, then explain the standing of the account "' . $mcpArguments->string('account')
            . '" and which transition may move it next.',
        )];
    }
}
