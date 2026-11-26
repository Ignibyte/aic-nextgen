<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpPromptInfo;
use AIC\Mcp\Domain\McpPromptResult;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpReadResult;
use AIC\Mcp\Domain\McpRequest;
use AIC\Mcp\Domain\McpToolResult;

/**
 * The three calls of an MCP server (#316): `tools/call`, `resources/read` and
 * `prompts/get`, each on an entry the caller may use; any other is unknown to
 * it, so a caller learns nothing of what it may not use. A tool's refusal is its
 * result, with `isError`; the arguments' faults are the server's to answer. A
 * tool call goes through the capability registry (#332).
 */
final readonly class McpCalls
{
    /**
     * @param Capabilities $capabilities The registry, which runs each tool call (#332).
     */
    public function __construct(
        private Capabilities $capabilities,
    ) {
    }

    /**
     * @param McpRequest $mcpRequest The `tools/call`, with `aic/callId` and `aic/dryRun` in its `_meta` (#332).
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who calls.
     *
     * @throws McpProtocolException When it names no tool the caller may call, its call id is not one or names
     *                              another tool's call, or the tool failed.
     * @throws McpArgumentException When the arguments do not fit the tool's input.
     * @throws QueryException       When the audit or the lock fails.
     *
     * @return McpToolResult The tool's output, or its refusal.
     */
    public function call(McpRequest $mcpRequest, McpCatalog $mcpCatalog, Actor $actor): McpToolResult
    {
        $capabilityCall = new CapabilityCall(
            $mcpRequest->param('name'),
            $mcpRequest->argumentText(),
            $mcpRequest->metaText('aic/callId'),
            $mcpRequest->metaFlag('aic/dryRun'),
        );

        return new McpToolResult($this->capabilities->call($mcpCatalog, $capabilityCall, $actor));
    }

    /**
     * @param McpRequest $mcpRequest The `resources/read`.
     * @param McpCatalog $mcpCatalog The resources.
     * @param Actor      $actor      Who reads.
     *
     * @throws McpProtocolException When its URI names no resource the caller may read (-32002).
     *
     * @return McpReadResult The resource's text.
     */
    public function read(McpRequest $mcpRequest, McpCatalog $mcpCatalog, Actor $actor): McpReadResult
    {
        $uri = $mcpRequest->param('uri');

        foreach ($mcpCatalog->resources as $mcpResourceRead) {
            $mcpResourceInfo = $mcpResourceRead->resourceInfo();

            if ($mcpResourceInfo->uri === $uri && $actor->can($mcpResourceInfo->needs)) {
                return new McpReadResult($mcpResourceInfo, $mcpResourceRead->resourceText());
            }
        }

        throw new McpProtocolException('Resource not found: ' . $uri . '.', McpProtocolException::RESOURCE_NOT_FOUND);
    }

    /**
     * @param McpRequest $mcpRequest The `prompts/get`.
     * @param McpCatalog $mcpCatalog The prompts.
     * @param Actor      $actor      Who asks.
     *
     * @throws McpProtocolException When it names no prompt the caller may get.
     * @throws McpArgumentException When an argument is missing, not a string, or not one the prompt takes.
     *
     * @return McpPromptResult The prompt's messages.
     */
    public function prompt(McpRequest $mcpRequest, McpCatalog $mcpCatalog, Actor $actor): McpPromptResult
    {
        $name = $mcpRequest->param('name');

        foreach ($mcpCatalog->prompts as $mcpPromptGet) {
            $mcpPromptInfo = $mcpPromptGet->promptInfo();

            if ($mcpPromptInfo->name === $name && $actor->can($mcpPromptInfo->needs)) {
                $mcpArguments = $this->filled($mcpPromptInfo, $mcpRequest->arguments());

                return new McpPromptResult($mcpPromptInfo->description, $mcpPromptGet->promptMessages($mcpArguments));
            }
        }

        throw new McpProtocolException('Invalid params: no prompt is named "' . $name . '".', McpProtocolException::INVALID_PARAMS);
    }

    /**
     * @param McpPromptInfo $mcpPromptInfo A prompt.
     * @param McpArguments  $mcpArguments  The arguments the client filled.
     *
     * @throws McpArgumentException When one is not the prompt's, or a required one is missing or not a string.
     *
     * @return McpArguments The arguments, checked.
     */
    private function filled(McpPromptInfo $mcpPromptInfo, McpArguments $mcpArguments): McpArguments
    {
        $names = [];

        foreach ($mcpPromptInfo->arguments as $mcpPromptArgument) {
            $names[] = $mcpPromptArgument->name;

            if (!$mcpPromptArgument->required && !$mcpArguments->has($mcpPromptArgument->name)) {
                continue;
            }

            $mcpArguments->string($mcpPromptArgument->name);
        }

        $mcpArguments->only($names);

        return $mcpArguments;
    }
}
