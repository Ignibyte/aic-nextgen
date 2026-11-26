<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\ToolRefusedException;
use AIC\Mcp\Domain\CallOutcome;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\CapabilityResult;
use AIC\Mcp\Domain\McpJson;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpToolCall;
use RuntimeException;

/**
 * One capability call (#332), in a savepoint of the registry's transaction:
 * the tool's output encoded once, kept on a call and rolled back on a dry run;
 * a refusal, arguments that do not fit, or a failure roll the tool's writes
 * back, and each ends as the result its audit row records. `answered()` then
 * turns the two that are not answers into the errors the caller gets.
 */
final readonly class ToolRun
{
    /**
     * @param Database $database The database, inside the registry's transaction.
     * @param McpJson  $mcpJson  Encodes the output.
     */
    public function __construct(
        private Database $database,
        private McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @param McpToolCall    $mcpToolCall    The tool's adapter.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @throws QueryException When the savepoint cannot be set or ended.
     *
     * @return CapabilityResult How it ended.
     */
    public function run(McpToolCall $mcpToolCall, CapabilityCall $capabilityCall, Actor $actor): CapabilityResult
    {
        if ($capabilityCall->dryRun && !$mcpToolCall->toolInfo()->dryRun) {
            $why = $capabilityCall->tool . ' cannot run dry: it has effects outside the database.';

            return new CapabilityResult(CallOutcome::Refused, '', $why, true);
        }

        $this->database->beginTransaction();
        $kept = false;

        try {
            $capabilityResult = $this->attempt($mcpToolCall, $capabilityCall, $actor);

            if ($capabilityResult->callOutcome === CallOutcome::Done && !$capabilityCall->dryRun) {
                $this->database->commit();
                $kept = true;
            }
        } finally {
            if (!$kept) {
                $this->database->rollBack();
            }
        }

        return $capabilityResult;
    }

    /**
     * @param CapabilityResult $capabilityResult A result, recorded.
     *
     * @throws McpArgumentException When the arguments did not fit.
     * @throws McpProtocolException When the tool failed (-32603), its reason kept for the audit alone.
     *
     * @return CapabilityResult It, when it is an answer: done or refused.
     */
    public function answered(CapabilityResult $capabilityResult): CapabilityResult
    {
        if ($capabilityResult->callOutcome === CallOutcome::Invalid) {
            throw new McpArgumentException($capabilityResult->refusal);
        }

        if ($capabilityResult->callOutcome === CallOutcome::Failed) {
            throw new McpProtocolException('Internal error: the tool failed.', McpProtocolException::INTERNAL_ERROR);
        }

        return $capabilityResult;
    }

    /**
     * @param McpToolCall    $mcpToolCall    The tool's adapter.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     *
     * @return CapabilityResult The output, or how the tool or its arguments ended the call.
     */
    private function attempt(McpToolCall $mcpToolCall, CapabilityCall $capabilityCall, Actor $actor): CapabilityResult
    {
        $dryRun = $capabilityCall->dryRun;

        try {
            $output = $this->mcpJson->encode($mcpToolCall->callTool($capabilityCall->input(), $actor));

            return new CapabilityResult(CallOutcome::Done, $output, '', $dryRun);
        } catch (ToolRefusedException $toolRefusedException) {
            return new CapabilityResult(CallOutcome::Refused, '', $toolRefusedException->getMessage(), $dryRun);
        } catch (McpArgumentException $mcpArgumentException) {
            return new CapabilityResult(CallOutcome::Invalid, '', $mcpArgumentException->getMessage(), $dryRun);
        } catch (RuntimeException $runtimeException) {
            $why = $runtimeException::class . ': ' . $runtimeException->getMessage();

            return new CapabilityResult(CallOutcome::Failed, '', $why, $dryRun);
        }
    }
}
