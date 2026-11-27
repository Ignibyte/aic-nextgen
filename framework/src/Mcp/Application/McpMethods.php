<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpErrorReply;
use AIC\Mcp\Domain\McpInitializeResult;
use AIC\Mcp\Domain\McpJson;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpReply;
use AIC\Mcp\Domain\McpRequest;
use JsonSerializable;
use RuntimeException;
use stdClass;

use function in_array;

/**
 * The methods an MCP server answers (#316): `initialize` (agreeing a protocol
 * version), `ping`, the three lists (each holding only what the caller may
 * use) and the three calls, which `McpCalls` answers. It writes each reply as
 * JSON text, so a tool call's stored output is answered as it was (#332).
 */
final readonly class McpMethods
{
    /**
     * The versions it speaks, newest first; a client asking for another is
     * offered the newest.
     */
    public const array VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /**
     * @param McpCalls $mcpCalls The calls.
     * @param McpLists $mcpLists The lists.
     * @param McpJson  $mcpJson  Writes the reply.
     */
    public function __construct(
        private McpCalls $mcpCalls,
        private McpLists $mcpLists = new McpLists(),
        private McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @param int|string $id         The request's id.
     * @param McpRequest $mcpRequest The request.
     * @param McpCatalog $mcpCatalog The tools, resources and prompts it serves.
     * @param Actor      $actor      Who asks.
     * @param string     $name       The app's name, which `initialize` gives.
     *
     * @return string Its result, or its error: one the server named, the arguments', or an internal one, encoded; a
     *                tool call's reply is written around the call's encoded output (#332).
     */
    public function reply(
        int|string $id,
        McpRequest $mcpRequest,
        McpCatalog $mcpCatalog,
        Actor $actor,
        string $name,
    ): string {
        try {
            if ($mcpRequest->method() === 'tools/call') {
                return $this->mcpJson->reply($id, $this->mcpCalls->call($mcpRequest, $mcpCatalog, $actor)->text());
            }

            return $this->mcpJson->encode(new McpReply($id, $this->result($mcpRequest, $mcpCatalog, $actor, $name)));
        } catch (McpProtocolException $mcpProtocolException) {
            return $this->mcpJson->encode(new McpErrorReply($id, $mcpProtocolException->getCode(), $mcpProtocolException->getMessage()));
        } catch (McpArgumentException $mcpArgumentException) {
            $why = 'Invalid params: ' . $mcpArgumentException->getMessage();

            return $this->mcpJson->encode(new McpErrorReply($id, McpProtocolException::INVALID_PARAMS, $why));
        } catch (RuntimeException) {
            $why = 'Internal error: the server could not answer.';

            return $this->mcpJson->encode(new McpErrorReply($id, McpProtocolException::INTERNAL_ERROR, $why));
        }
    }

    /**
     * @param int|string $id         The request's id.
     * @param McpRequest $mcpRequest A request from a client that reads an event stream.
     * @param McpCatalog $mcpCatalog The tools.
     * @param Actor      $actor      Who asks.
     *
     * @return ToolProgress|null A stepped tool call's events, when it asks for progress (#333); null otherwise.
     */
    public function events(int|string $id, McpRequest $mcpRequest, McpCatalog $mcpCatalog, Actor $actor): ?ToolProgress
    {
        return $this->mcpCalls->progress($id, $mcpRequest, $mcpCatalog, $actor);
    }

    /**
     * @param McpRequest $mcpRequest The request.
     * @param McpCatalog $mcpCatalog The app's tools, resources and prompts.
     * @param Actor      $actor      Who asks.
     * @param string     $name       The app's name, which `initialize` gives.
     *
     * @throws McpProtocolException When the method is not one it answers, or the params do not fit it.
     * @throws McpArgumentException When a call's arguments do not fit.
     *
     * @return JsonSerializable|stdClass The method's result; an empty object for `ping`.
     */
    private function result(
        McpRequest $mcpRequest,
        McpCatalog $mcpCatalog,
        Actor $actor,
        string $name,
    ): JsonSerializable|stdClass {
        return match ($mcpRequest->method()) {
            'initialize' => new McpInitializeResult(
                $this->version($mcpRequest->optional('protocolVersion')),
                $name,
            ),
            'ping' => new stdClass(),
            'tools/list' => $this->mcpLists->visible('tools', $mcpCatalog->toolInfos(), $actor),
            'resources/list' => $this->mcpLists->visible('resources', $mcpCatalog->resourceInfos(), $actor),
            'prompts/list' => $this->mcpLists->visible('prompts', $mcpCatalog->promptInfos(), $actor),
            'resources/read' => $this->mcpCalls->read($mcpRequest, $mcpCatalog, $actor),
            'prompts/get' => $this->mcpCalls->prompt($mcpRequest, $mcpCatalog, $actor),
            default => throw new McpProtocolException(
                'Method not found: ' . $mcpRequest->method() . '.',
                McpProtocolException::METHOD_NOT_FOUND,
            ),
        };
    }

    /**
     * @param string $asked The version the client asked for; empty for none.
     *
     * @return string It, when the server speaks it; the newest otherwise.
     */
    private function version(string $asked): string
    {
        return in_array($asked, self::VERSIONS, true)
            ? $asked
            : self::VERSIONS[0];
    }
}
