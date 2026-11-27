<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Application\McpStream;
use App\Generated\LedgerMcp;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The fixture's MCP GET stream (#333): `GET /mcp`, under `ledger.mcp` as the
 * POST is, tells a client when a list of what the ledger serves it changed.
 */
final readonly class LedgerMcpStream implements Stream
{
    /**
     * @param McpStream $mcpStream The stream.
     * @param LedgerMcp $ledgerMcp The ledger's tools, resources and prompts.
     */
    public function __construct(
        private McpStream $mcpStream,
        private LedgerMcp $ledgerMcp,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The GET, perhaps with `Last-Event-ID`.
     *
     * @throws InvalidArgumentException When the channel is not one.
     * @throws QueryException           When a slot cannot be asked for.
     *
     * @return StreamResponse The stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->mcpStream->stream($serverRequest, $this->ledgerMcp->catalog());
    }
}
