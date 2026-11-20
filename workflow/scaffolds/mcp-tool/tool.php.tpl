<?php

declare(strict_types=1);

namespace {namespace}\Mcp;

use AIC\Mcp\Attribute\ToolInfo;
use AIC\Mcp\Contracts\McpTool;

/**
 * An MCP tool, made by bin/aic make:mcp-tool (#316). The build reads its
 * `call()` signature, writes its input and output schemas from the two value
 * classes, and writes its adapter into the module's generated catalog, which
 * the module's MCP endpoint serves.
 */
#[ToolInfo(
    name: '{name}',
    description: 'Answer a query. Say what the tool does and when an agent should call it.',
    needs: '{permission}',
)]
final readonly class {class} implements McpTool
{
    /**
     * @param {stem}Input ${variable}Input What the agent asks.
     *
     * @return {stem}Output The answer.
     */
    public function call({stem}Input ${variable}Input): {stem}Output
    {
        return new {stem}Output('You asked: ' . ${variable}Input->query);
    }
}
