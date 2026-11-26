<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;

use function is_int;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * JSON as the MCP server writes it (#332), with the flags `JsonResponse` uses,
 * for the replies it assembles as text around a tool's encoded output.
 */
final readonly class McpJson
{
    /**
     * @param JsonSerializable $jsonSerializable A value.
     *
     * @return string Its JSON.
     */
    public function encode(JsonSerializable $jsonSerializable): string
    {
        return (string) json_encode($jsonSerializable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $text A string.
     *
     * @return string It as a JSON string.
     */
    public function text(string $text): string
    {
        return (string) json_encode($text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param int|string $id     A request's id.
     * @param string     $result The result, encoded.
     *
     * @return string The JSON-RPC reply around it.
     */
    public function reply(int|string $id, string $result): string
    {
        $encodedId = is_int($id)
            ? (string) $id
            : $this->text($id);

        return '{"jsonrpc":"2.0","id":' . $encodedId . ',"result":' . $result . '}';
    }
}
