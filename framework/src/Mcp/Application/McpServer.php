<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Application\Actors;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\ApiAccepted;
use AIC\Controller\Domain\ApiEvents;
use AIC\Controller\Domain\JsonResponse;
use AIC\Controller\Domain\JsonText;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpErrorReply;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpRequest;
use Psr\Http\Message\ServerRequestInterface;

use function str_contains;

/**
 * An app's MCP server (#316): MCP 2025-06-18's JSON-RPC over Streamable HTTP,
 * answered as JSON. A module routes `POST /mcp` to its own Api, under the
 * `needs:` that admits a client, and hands each request here with the catalog
 * of the generated `App\Generated\<Module>Mcp`. One message per POST: a request gets its
 * reply, a notification a `202` with no body, a body that is not one JSON-RPC
 * message a `400` with the error. A stepped tool's call with a progress token
 * answers an event stream instead (#333), its progress then its reply. It
 * keeps nothing between requests, so it sends no `Mcp-Session-Id`; the GET
 * stream is `McpStream`'s.
 */
final readonly class McpServer
{
    /**
     * @param Actors     $actors     Who asks, from the request's bearer token or session.
     * @param AppConfig  $appConfig  The app's settings, for the name `initialize` gives.
     * @param McpMethods $mcpMethods The methods, whose tool calls go through the capability registry (#332).
     */
    public function __construct(
        private Actors $actors,
        private AppConfig $appConfig,
        private McpMethods $mcpMethods,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The POST.
     * @param McpCatalog             $mcpCatalog    The tools, resources and prompts it serves.
     *
     * @throws QueryException When the caller's roles or token cannot be read.
     *
     * @return ApiResponse The reply, the `202`, or the `400`; a stepped tool call's events for a client that sends a
     *                     progress token and reads `text/event-stream` (#333).
     */
    public function answer(ServerRequestInterface $serverRequest, McpCatalog $mcpCatalog): ApiResponse
    {
        try {
            $mcpRequest = new McpRequest((string) $serverRequest->getBody());
        } catch (McpProtocolException $mcpProtocolException) {
            return new JsonResponse(new McpErrorReply(null, $mcpProtocolException->getCode(), $mcpProtocolException->getMessage()), 400);
        }

        $id = $mcpRequest->messageId();

        if ($id === null) {
            return new ApiAccepted();
        }

        $actor = $this->actors->actor($serverRequest);
        $toolProgress = str_contains($serverRequest->getHeaderLine('Accept'), 'text/event-stream')
            ? $this->mcpMethods->events($id, $mcpRequest, $mcpCatalog, $actor)
            : null;

        if ($toolProgress instanceof ToolProgress) {
            return new ApiEvents($toolProgress);
        }

        return new JsonText($this->mcpMethods->reply($id, $mcpRequest, $mcpCatalog, $actor, $this->appConfig->name));
    }
}
