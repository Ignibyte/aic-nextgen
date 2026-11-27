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

        try {
            $capabilityResult = $this->opened($mcpToolCall, $capabilityCall, $actor)
                ?? $this->closed($mcpToolCall, $capabilityCall, $actor);
        } finally {
            $this->abandoned();
        }

        return $this->toolRun->answered($capabilityResult);
    }

    /**
     * A call's first phase (#333): the transaction, the call id's lock and stored answer, a dry call's refusal,
     * or the savepoint the call runs in.
     *
     * @param McpToolCall    $mcpToolCall    The tool's adapter, from `tool()`.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @throws McpProtocolException When the id names another tool's call.
     * @throws QueryException       When the audit, the lock or the savepoint fails.
     *
     * @return CapabilityResult|null The stored answer or the refusal, recorded and committed; null while the call
     *                               is open, for its steps and `closed()`.
     */
    public function opened(McpToolCall $mcpToolCall, CapabilityCall $capabilityCall, Actor $actor): ?CapabilityResult
    {
        $this->database->beginTransaction();
        $capabilityResult = $this->stored($capabilityCall, $actor) ?? $this->toolRun->opened($mcpToolCall, $capabilityCall);

        if ($capabilityResult instanceof CapabilityResult) {
            $this->kept($capabilityCall, $actor, $capabilityResult);
        }

        return $capabilityResult;
    }

    /**
     * @param McpToolCall    $mcpToolCall    The tool's adapter.
     * @param CapabilityCall $capabilityCall The open call.
     * @param Actor          $actor          Who calls.
     * @param int            $step           Which step, from 0.
     *
     * @return CapabilityResult Done with the step's message, or how the step ended the call.
     */
    public function step(
        McpToolCall $mcpToolCall,
        CapabilityCall $capabilityCall,
        Actor $actor,
        int $step,
    ): CapabilityResult {
        return $this->toolRun->attempt($mcpToolCall, $capabilityCall, $actor, $step);
    }

    /**
     * A call's last phase (#333): the call itself, unless a step ended it, the savepoint settled, the audit row
     * and the commit.
     *
     * @param McpToolCall           $mcpToolCall    The tool's adapter.
     * @param CapabilityCall        $capabilityCall The open call.
     * @param Actor                 $actor          Who calls.
     * @param CapabilityResult|null $ended          How a step ended it; null to run the call.
     *
     * @throws QueryException When the savepoint, the audit or the commit fails.
     *
     * @return CapabilityResult How it ended, recorded and committed.
     */
    public function closed(
        McpToolCall $mcpToolCall,
        CapabilityCall $capabilityCall,
        Actor $actor,
        ?CapabilityResult $ended = null,
    ): CapabilityResult {
        $capabilityResult = $this->toolRun->closed($mcpToolCall, $capabilityCall, $actor, $ended);
        $this->kept($capabilityCall, $actor, $capabilityResult);

        return $capabilityResult;
    }

    /**
     * @param CapabilityResult $capabilityResult A result, recorded.
     *
     * @throws McpArgumentException When the arguments did not fit.
     * @throws McpProtocolException When the tool failed (-32603).
     *
     * @return CapabilityResult It, when it is an answer: done or refused.
     */
    public function answered(CapabilityResult $capabilityResult): CapabilityResult
    {
        return $this->toolRun->answered($capabilityResult);
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
    public function tool(string $name, McpCatalog $mcpCatalog, Actor $actor): McpToolCall
    {
        foreach ($mcpCatalog->tools as $mcpToolCall) {
            $mcpToolInfo = $mcpToolCall->toolInfo();

            if ($mcpToolInfo->name === $name && $actor->can($mcpToolInfo->needs)) {
                return $mcpToolCall;
            }
        }

        throw new McpProtocolException('Invalid params: no tool is named "' . $name . '".', McpProtocolException::INVALID_PARAMS);
    }

    /**
     * Rolls back a call left open by an error.
     *
     * @throws QueryException When the roll back fails.
     */
    private function abandoned(): void
    {
        if (!$this->database->inTransaction()) {
            return;
        }

        $this->database->rollBack();
    }

    /**
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @throws McpProtocolException When the id names another tool's call.
     * @throws QueryException       When the lock or the read fails.
     *
     * @return CapabilityResult|null The answer stored under the call's id, once its lock is held; null for a call
     *                               that claims no id or a new one.
     */
    private function stored(CapabilityCall $capabilityCall, Actor $actor): ?CapabilityResult
    {
        if (!$capabilityCall->claims()) {
            return null;
        }

        $this->mcpCallLog->lock($actor, $capabilityCall);

        foreach ($this->mcpCallLog->stored($actor, $capabilityCall) as $stored) {
            return $stored;
        }

        return null;
    }

    /**
     * @param CapabilityCall   $capabilityCall   The call.
     * @param Actor            $actor            Who calls.
     * @param CapabilityResult $capabilityResult How it ended.
     *
     * @throws QueryException When the audit or the commit fails.
     */
    private function kept(CapabilityCall $capabilityCall, Actor $actor, CapabilityResult $capabilityResult): void
    {
        $this->mcpCallLog->record($actor, $capabilityCall, $capabilityResult);
        $this->database->commit();
    }
}
