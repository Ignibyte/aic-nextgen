<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\CapabilityResult;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpToolCall;
use AIC\Mcp\Domain\McpToolInfo;

/**
 * The capability registry (#332): the one path a tool is called by, for an
 * MCP client's `tools/call` and for an app's own assistant alike. It finds the
 * tool the caller may call (an assistant's `Actor::within()` ceiling
 * included), then, in one transaction, holds the call id's lock and answers
 * an id already used with its stored answer, or runs the call (`ToolRun`) and
 * writes its audit row, committed with the tool's own writes.
 *
 * @api
 */
final readonly class Capabilities
{
    /**
     * @param McpCallLog $mcpCallLog The audit and the call ids.
     * @param Database   $database   The database, for the transaction.
     * @param ToolRun    $toolRun    Runs one call in a savepoint.
     */
    public function __construct(
        private McpCallLog $mcpCallLog,
        private Database $database,
        private ToolRun $toolRun,
    ) {
    }

    /**
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who would call.
     *
     * @return list<McpToolInfo> The tools it may call, in catalog order.
     */
    public function visible(McpCatalog $mcpCatalog, Actor $actor): array
    {
        $visible = [];

        foreach ($mcpCatalog->toolInfos() as $mcpToolInfo) {
            if (!$actor->can($mcpToolInfo->needs)) {
                continue;
            }

            $visible[] = $mcpToolInfo;
        }

        return $visible;
    }

    /**
     * @param McpCatalog     $mcpCatalog     The tools.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @throws McpProtocolException When it names no tool the caller may call, its id names another tool's call
     *                              (-32602), or the tool failed (-32603).
     * @throws McpArgumentException When the arguments do not fit the tool's input.
     * @throws QueryException       When the audit or the lock fails.
     *
     * @return CapabilityResult The tool's output or refusal, a dry run's, or the stored answer of the same id.
     */
    public function call(McpCatalog $mcpCatalog, CapabilityCall $capabilityCall, Actor $actor): CapabilityResult
    {
        $mcpToolCall = $this->tool($capabilityCall->tool, $mcpCatalog, $actor);
        $this->database->beginTransaction();
        $committed = false;

        try {
            $capabilityResult = $this->held($mcpToolCall, $capabilityCall, $actor);
            $this->database->commit();
            $committed = true;
        } finally {
            if (!$committed && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
        }

        return $this->toolRun->answered($capabilityResult);
    }

    /**
     * @param McpToolCall    $mcpToolCall    The tool's adapter.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @throws McpProtocolException When the id names another tool's call.
     * @throws QueryException       When the audit or the lock fails.
     *
     * @return CapabilityResult The stored answer, or the call's, recorded.
     */
    private function held(McpToolCall $mcpToolCall, CapabilityCall $capabilityCall, Actor $actor): CapabilityResult
    {
        if ($capabilityCall->claims()) {
            $this->mcpCallLog->lock($actor, $capabilityCall);

            foreach ($this->mcpCallLog->stored($actor, $capabilityCall) as $stored) {
                $this->mcpCallLog->record($actor, $capabilityCall, $stored);

                return $stored;
            }
        }

        $capabilityResult = $this->toolRun->run($mcpToolCall, $capabilityCall, $actor);
        $this->mcpCallLog->record($actor, $capabilityCall, $capabilityResult);

        return $capabilityResult;
    }

    /**
     * @param string     $name       A tool's name.
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who calls.
     *
     * @throws McpProtocolException When no tool the caller may call has it (-32602).
     *
     * @return McpToolCall The tool's adapter.
     */
    private function tool(string $name, McpCatalog $mcpCatalog, Actor $actor): McpToolCall
    {
        foreach ($mcpCatalog->tools as $mcpToolCall) {
            $mcpToolInfo = $mcpToolCall->toolInfo();

            if ($mcpToolInfo->name === $name && $actor->can($mcpToolInfo->needs)) {
                return $mcpToolCall;
            }
        }

        throw new McpProtocolException('Invalid params: no tool is named "' . $name . '".', McpProtocolException::INVALID_PARAMS);
    }
}
