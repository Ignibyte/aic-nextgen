<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Application\Actors;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\ApiAccepted;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpErrorReply;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An app's MCP server (#316): MCP 2025-06-18's JSON-RPC over Streamable HTTP,
 * answered as JSON. A module routes `POST /mcp` to its own Api, under the
 * `needs:` that admits a client, and hands each request here with the catalog
 * of the generated `App\Generated\<Module>Mcp`. One message per POST: a request gets its
 * reply, a notification a `202` with no body, a body that is not one JSON-RPC
 * message a `400` with the error. It keeps nothing between requests, so it sends
 * no `Mcp-Session-Id` and offers no GET stream.
 */
final readonly class McpServer
{
    /**
     * @param Actors     $actors     Who asks, from the request's bearer token or session.
     * @param AppConfig  $appConfig  The app's settings, for the name `initialize` gives.
     * @param McpMethods $mcpMethods The methods.
     */
    public function __construct(
        private Actors $actors,
        private AppConfig $appConfig,
        private McpMethods $mcpMethods = new McpMethods(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The POST.
     * @param McpCatalog             $mcpCatalog    The tools, resources and prompts it serves.
     *
     * @throws QueryException When the caller's roles or token cannot be read.
     *
     * @return ApiResponse The reply, the `202`, or the `400`.
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

        return new JsonResponse($this->mcpMethods->reply($id, $mcpRequest, $mcpCatalog, $actor, $this->appConfig->name));
    }
}
