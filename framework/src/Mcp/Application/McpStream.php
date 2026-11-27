<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Application\Actors;
use AIC\Broadcast\Application\Broadcasts;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Domain\McpCatalog;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function explode;

/**
 * An app's MCP GET stream (#333): a module routes `GET /mcp` to its own
 * Stream, under the same `needs:` as its `POST /mcp`, which hands the request
 * here with the same catalog. It is a broadcast stream on `aic.mcp` (#314),
 * held, capped and resumed as one, that tells the client when a list it may
 * use changed (`McpListEvents`).
 *
 * @api
 */
final readonly class McpStream
{
    /**
     * The channel the stream holds on.
     */
    public const string CHANNEL = 'aic.mcp';

    /**
     * @param Broadcasts $broadcasts Makes the channel's events.
     * @param Actors     $actors     Resolves the caller at each check.
     */
    public function __construct(
        private Broadcasts $broadcasts,
        private Actors $actors,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The GET, perhaps with `Last-Event-ID` (`<n>:<prints>`).
     * @param McpCatalog             $mcpCatalog    The tools, resources and prompts it serves.
     *
     * @throws InvalidArgumentException When the channel is not one.
     * @throws QueryException           When a slot cannot be asked for.
     *
     * @return StreamResponse The stream.
     */
    public function stream(ServerRequestInterface $serverRequest, McpCatalog $mcpCatalog): StreamResponse
    {
        $seen = explode(':', $serverRequest->getHeaderLine('Last-Event-ID'), 2);
        $after = ctype_digit($seen[0])
            ? $seen[0]
            : '';
        $prints = $seen[1] ?? '';
        $eventSource = $this->broadcasts->source(new Channel(self::CHANNEL), new McpNoticeEvents(), $after);

        return new EventStream(new McpListEvents($eventSource, $this->actors, $serverRequest, $mcpCatalog, $after, $prints));
    }
}
