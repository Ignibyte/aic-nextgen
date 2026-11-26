<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;
use AIC\Mcp\Contracts\McpPrompt;
use Override;

/**
 * A fixture MCP prompt (#334): it asks the agent to file an entry under one of
 * the ledger's categories, with its amount when given.
 */
#[PromptInfo(
    name: 'ledger.categorize',
    description: 'Ask which ledger category an entry belongs under.',
    needs: 'ledger.mcp',
    arguments: ['entry' => 'What the entry was for.', 'amount' => 'Its amount, in cents.'],
    optional: ['amount'],
)]
final readonly class LedgerCategorizePrompt implements McpPrompt
{
    /**
     * @param McpArguments $mcpArguments The entry and, if given, its amount.
     *
     * @return list<McpMessage> A message from the user, and one more for the amount.
     */
    #[Override]
    public function messages(McpArguments $mcpArguments): array
    {
        $messages = [new McpMessage('Pick the ledger://categories name that fits: ' . $mcpArguments->string('entry') . '.')];

        if ($mcpArguments->has('amount')) {
            $messages[] = new McpMessage('It came to ' . $mcpArguments->string('amount') . ' cents.');
        }

        return $messages;
    }
}
