<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;

/**
 * One prompt's adapter (#316), written by the build and built with the prompt:
 * its descriptor, from `#[PromptInfo]`, and its messages.
 */
interface McpPromptGet
{
    /**
     * @return McpPromptInfo The prompt, as `prompts/list` shows it.
     */
    public function promptInfo(): McpPromptInfo;

    /**
     * @param McpArguments $mcpArguments The arguments the client filled, checked against the info's.
     *
     * @throws McpArgumentException When the prompt cannot read one it needs.
     *
     * @return list<McpMessage> Its messages.
     */
    public function promptMessages(McpArguments $mcpArguments): array;
}
