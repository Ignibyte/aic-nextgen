<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * A JSON-RPC error (#316): the request's id (null when it could not be read),
 * the code and the message.
 */
final readonly class McpErrorReply implements JsonSerializable
{
    /**
     * @param int|string|null $id      The request's id, or null.
     * @param int             $code    JSON-RPC's code (-32700 to -32600), or MCP's.
     * @param string          $message What went wrong, for the agent.
     */
    public function __construct(
        private int|string|null $id,
        private int $code,
        private string $message,
    ) {
    }

    /**
     * @return array{jsonrpc: string, id: int|string|null, error: array{code: int, message: string}} The reply.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['jsonrpc' => '2.0', 'id' => $this->id, 'error' => ['code' => $this->code, 'message' => $this->message]];
    }
}
