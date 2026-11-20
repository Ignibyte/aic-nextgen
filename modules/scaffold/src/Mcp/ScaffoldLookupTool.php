<?php

declare(strict_types=1);

namespace App\Scaffold\Mcp;

use AIC\Mcp\Attribute\ToolInfo;
use AIC\Mcp\Contracts\McpTool;

/**
 * An MCP tool, made by bin/aic make:mcp-tool (#316). The build reads its
 * `call()` signature, writes its input and output schemas from the two value
 * classes, and writes its adapter into the module's generated catalog, which
 * the module's MCP endpoint serves.
 */
#[ToolInfo(
    name: 'scaffold.scaffold-lookup',
    description: 'Answer a query. Say what the tool does and when an agent should call it.',
    needs: 'scaffold.scaffold-lookup',
)]
final readonly class ScaffoldLookupTool implements McpTool
{
    /**
     * @param ScaffoldLookupInput $scaffoldLookupInput What the agent asks.
     *
     * @return ScaffoldLookupOutput The answer.
     */
    public function call(ScaffoldLookupInput $scaffoldLookupInput): ScaffoldLookupOutput
    {
        return new ScaffoldLookupOutput('You asked: ' . $scaffoldLookupInput->query);
    }
}
