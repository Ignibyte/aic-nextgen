<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\ToolRefusedException;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpPromptInfo;
use AIC\Mcp\Domain\McpPromptResult;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpReadResult;
use AIC\Mcp\Domain\McpRequest;
use AIC\Mcp\Domain\McpToolCall;
use AIC\Mcp\Domain\McpToolResult;

/**
 * The three calls of an MCP server (#316): `tools/call`, `resources/read` and
 * `prompts/get`, each on an entry the caller may use; any other is unknown to
 * it, so a caller learns nothing of what it may not use. A tool's refusal is its
 * result, with `isError`; the arguments' faults are the server's to answer.
 */
final readonly class McpCalls
{
    /**
     * @param McpRequest $mcpRequest The `tools/call`.
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who calls.
     *
     * @throws McpProtocolException When it names no tool the caller may call.
     * @throws McpArgumentException When the arguments do not fit the tool's input.
     *
     * @return McpToolResult The tool's output, or its refusal.
     */
    public function call(McpRequest $mcpRequest, McpCatalog $mcpCatalog, Actor $actor): McpToolResult
    {
        $mcpToolCall = $this->tool($mcpRequest->param('name'), $mcpCatalog, $actor);

        try {
            return new McpToolResult($mcpToolCall->callTool($mcpRequest->arguments(), $actor));
        } catch (ToolRefusedException $toolRefusedException) {
            return new McpToolResult(null, $toolRefusedException->getMessage());
        }
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

    /**
     * @param string     $name       A tool's name.
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who calls.
     *
     * @throws McpProtocolException When no tool the caller may call has it.
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
