<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\ToolRefusedException;
use JsonSerializable;

/**
 * One tool's adapter (#316), written by the build and built with the tool: the
 * tool's descriptor, and the call that checks the arguments, builds the tool's
 * input from them, calls the tool and wraps its output in the result whose JSON
 * the output schema describes. A stepped tool's adapter (#333) is also its
 * `McpSteppedCall`.
 */
interface McpToolCall
{
    /**
     * @return McpToolInfo The tool, as `tools/list` shows it.
     */
    public function toolInfo(): McpToolInfo;

    /**
     * @param McpArguments $mcpArguments The call's arguments.
     * @param Actor        $actor        Who calls.
     *
     * @throws McpArgumentException When the arguments do not fit the input.
     * @throws ToolRefusedException When the tool refuses the call.
     *
     * @return JsonSerializable The tool's output, as its structured content.
     */
    public function callTool(McpArguments $mcpArguments, Actor $actor): JsonSerializable;

    /**
     * @return McpSteppedCall|null The adapter itself when the tool runs in steps; null otherwise.
     */
    public function stepped(): ?McpSteppedCall;
}
