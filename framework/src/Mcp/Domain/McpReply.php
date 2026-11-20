<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;
use stdClass;

/**
 * A JSON-RPC result (#316): the request's id and what its method answered.
 */
final readonly class McpReply implements JsonSerializable
{
    /**
     * @param int|string                $id               The request's id.
     * @param JsonSerializable|stdClass $jsonSerializable What its method answered; an empty object for `ping`.
     */
    public function __construct(
        private int|string $id,
        private JsonSerializable|stdClass $jsonSerializable,
    ) {
    }

    /**
     * @return array{jsonrpc: string, id: int|string, result: JsonSerializable|stdClass} The reply.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['jsonrpc' => '2.0', 'id' => $this->id, 'result' => $this->jsonSerializable];
    }
}
