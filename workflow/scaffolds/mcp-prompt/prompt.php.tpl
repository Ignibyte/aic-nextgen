<?php

declare(strict_types=1);

namespace {namespace}\Mcp;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;
use AIC\Mcp\Contracts\McpPrompt;
use Override;

/**
 * An MCP prompt, made by bin/aic make:mcp-prompt (#334). The build writes its
 * adapter into the module's generated catalog, which the module's MCP endpoint
 * serves; an agent lists it and gets its messages with its arguments.
 */
#[PromptInfo(
    name: '{name}',
    description: 'Say what the prompt asks for and when an agent should use it.',
    needs: '{permission}',
    arguments: ['topic' => 'What the prompt is about.'],
)]
final readonly class {class} implements McpPrompt
{
    /**
     * @param McpArguments $mcpArguments The topic.
     *
     * @return list<McpMessage> One message from the user.
     */
    #[Override]
    public function messages(McpArguments $mcpArguments): array
    {
        return [new McpMessage('Tell me about ' . $mcpArguments->string('topic') . '.')];
    }
}
