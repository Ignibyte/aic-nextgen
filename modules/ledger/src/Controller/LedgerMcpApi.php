<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Application\McpServer;
use App\Generated\LedgerMcp;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The fixture's MCP endpoint (#316): `POST /mcp`, under `ledger.mcp`, serves
 * what the ledger's `src/Mcp/` holds, as the build wrote it into `LedgerMcp`.
 */
final readonly class LedgerMcpApi implements Api
{
    /**
     * @param McpServer $mcpServer The protocol.
     * @param LedgerMcp $ledgerMcp The ledger's tools, resources and prompts.
     */
    public function __construct(
        private McpServer $mcpServer,
        private LedgerMcp $ledgerMcp,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest One JSON-RPC message.
     *
     * @throws QueryException When the caller's token cannot be read.
     *
     * @return ApiResponse Its reply.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return $this->mcpServer->answer($serverRequest, $this->ledgerMcp->catalog());
    }
}
