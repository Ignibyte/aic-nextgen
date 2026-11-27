<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\ToolRefusedException;

/**
 * A stepped tool's adapter (#333), written by the build for a tool with
 * `steps()` and `step()` beside `call()`: it counts the call's steps and runs
 * each, so the caller can report progress between them before `callTool()`.
 */
interface McpSteppedCall extends McpToolCall
{
    /**
     * @param McpArguments $mcpArguments The call's arguments.
     *
     * @throws McpArgumentException When the arguments do not fit the input.
     *
     * @return int How many steps the call runs before `callTool()`.
     */
    public function steps(McpArguments $mcpArguments): int;

    /**
     * @param McpArguments $mcpArguments The call's arguments.
     * @param int          $step         Which step, from 0.
     * @param Actor        $actor        Who calls.
     *
     * @throws McpArgumentException When the arguments do not fit the input.
     * @throws ToolRefusedException When the tool refuses the call.
     *
     * @return string What the step did, as its progress message.
     */
    public function step(McpArguments $mcpArguments, int $step, Actor $actor): string;
}
