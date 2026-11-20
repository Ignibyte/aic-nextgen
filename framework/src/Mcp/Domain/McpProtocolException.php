<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use RuntimeException;

/**
 * A JSON-RPC message the server cannot answer as asked (#316): its code is
 * JSON-RPC's (or MCP's, for a resource it does not have) and its message is
 * what the error reply says.
 */
final class McpProtocolException extends RuntimeException
{
    public const int PARSE_ERROR = -32_700;

    public const int INVALID_REQUEST = -32_600;

    public const int METHOD_NOT_FOUND = -32_601;

    public const int INVALID_PARAMS = -32_602;

    public const int INTERNAL_ERROR = -32_603;

    public const int RESOURCE_NOT_FOUND = -32_002;
}
