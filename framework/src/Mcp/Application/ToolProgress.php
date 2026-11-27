<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\CallOutcome;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\CapabilityResult;
use AIC\Mcp\Domain\McpErrorReply;
use AIC\Mcp\Domain\McpJson;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpSteppedCall;
use AIC\Mcp\Domain\McpToolResult;
use Override;

/**
 * A stepped tool's call as the events of its POST's answer (#333): the body
 * pulls one step per event, inside the call's transaction and savepoint
 * (`Capabilities::opened()`), and each step leaves as a
 * `notifications/progress` once it ends; then the call (`closed()`) and its
 * JSON-RPC reply, the last event. A stored answer, a dry refusal, or a step
 * that ends the call answers at once. A connection lost mid-call ends the
 * request, and the database rolls the open call back.
 */
final class ToolProgress implements EventSource
{
    /**
     * The next step to run, from 0; -1 before the call is opened.
     */
    private int $step = -1;

    /**
     * How many steps the call runs, once it is opened.
     */
    private int $total = 0;

    /**
     * @param Capabilities   $capabilities   The registry, whose phases the call runs in.
     * @param McpSteppedCall $mcpSteppedCall The tool's adapter.
     * @param CapabilityCall $capabilityCall The call.
     * @param Actor          $actor          Who calls.
     * @param int|string     $id             The request's id.
     * @param string         $progressToken  The request's progress token, as JSON.
     * @param McpJson        $mcpJson        Writes the messages.
     */
    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly McpSteppedCall $mcpSteppedCall,
        private readonly CapabilityCall $capabilityCall,
        private readonly Actor $actor,
        private readonly int|string $id,
        private readonly string $progressToken,
        private readonly McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @throws QueryException When the audit, the lock or the transaction fails.
     *
     * @return ServerEvent The next step's progress, or the reply, last.
     */
    #[Override]
    public function next(): ServerEvent
    {
        try {
            return $this->advanced();
        } catch (McpProtocolException $mcpProtocolException) {
            return $this->last($this->mcpJson->encode(
                new McpErrorReply($this->id, $mcpProtocolException->getCode(), $mcpProtocolException->getMessage()),
            ));
        }
    }

    /**
     * @throws McpProtocolException When the call's id names another tool's call.
     * @throws QueryException       When the audit, the lock or the transaction fails.
     *
     * @return ServerEvent The next step's progress, or the reply.
     */
    private function advanced(): ServerEvent
    {
        if ($this->step < 0) {
            $this->step = 0;
            $opened = $this->capabilities->opened($this->mcpSteppedCall, $this->capabilityCall, $this->actor)
                ?? $this->counted();

            if ($opened instanceof CapabilityResult) {
                return $this->reply($opened);
            }
        }

        if ($this->step >= $this->total) {
            return $this->reply($this->capabilities->closed($this->mcpSteppedCall, $this->capabilityCall, $this->actor));
        }

        $capabilityResult = $this->capabilities->step($this->mcpSteppedCall, $this->capabilityCall, $this->actor, $this->step);
        $this->step += 1;

        if ($capabilityResult->callOutcome !== CallOutcome::Done) {
            return $this->ended($capabilityResult);
        }

        return $this->progress($capabilityResult->output);
    }

    /**
     * @throws QueryException When the call cannot be closed.
     *
     * @return CapabilityResult|null The call's end when its arguments do not fit; null with the steps counted.
     */
    private function counted(): ?CapabilityResult
    {
        try {
            $this->total = $this->mcpSteppedCall->steps($this->capabilityCall->input());

            return null;
        } catch (McpArgumentException $mcpArgumentException) {
            $why = $mcpArgumentException->getMessage();
            $capabilityResult = new CapabilityResult(CallOutcome::Invalid, '', $why, $this->capabilityCall->dryRun);

            return $this->capabilities->closed($this->mcpSteppedCall, $this->capabilityCall, $this->actor, $capabilityResult);
        }
    }

    /**
     * @param CapabilityResult $capabilityResult How a step ended the call.
     *
     * @throws McpProtocolException When the tool failed (-32603).
     * @throws QueryException       When the call cannot be closed.
     *
     * @return ServerEvent The reply, the call closed with that end.
     */
    private function ended(CapabilityResult $capabilityResult): ServerEvent
    {
        return $this->reply($this->capabilities->closed($this->mcpSteppedCall, $this->capabilityCall, $this->actor, $capabilityResult));
    }

    /**
     * @param string $message What the step did.
     *
     * @return ServerEvent The step's `notifications/progress`.
     */
    private function progress(string $message): ServerEvent
    {
        $params = '{"progressToken":' . $this->progressToken . ',"progress":' . $this->step . ',"total":' . $this->total
            . ',"message":' . $this->mcpJson->text($message) . '}';

        return new ServerEvent('message', '{"jsonrpc":"2.0","method":"notifications/progress","params":' . $params . '}', false);
    }

    /**
     * @param CapabilityResult $capabilityResult How the call ended, recorded.
     *
     * @throws McpProtocolException When the tool failed (-32603).
     *
     * @return ServerEvent The JSON-RPC reply: the result, or the arguments' error.
     */
    private function reply(CapabilityResult $capabilityResult): ServerEvent
    {
        try {
            $mcpToolResult = new McpToolResult($this->capabilities->answered($capabilityResult));

            return $this->last($this->mcpJson->reply($this->id, $mcpToolResult->text()));
        } catch (McpArgumentException $mcpArgumentException) {
            $why = 'Invalid params: ' . $mcpArgumentException->getMessage();

            return $this->last($this->mcpJson->encode(new McpErrorReply($this->id, McpProtocolException::INVALID_PARAMS, $why)));
        }
    }

    /**
     * @param string $message A JSON-RPC message.
     *
     * @return ServerEvent It as the stream's last event.
     */
    private function last(string $message): ServerEvent
    {
        return new ServerEvent('message', $message, true);
    }
}
