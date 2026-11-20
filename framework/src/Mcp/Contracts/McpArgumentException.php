<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

use RuntimeException;

/**
 * A call's arguments do not fit what it takes (#316): a field is missing, of
 * another type, not one of its choices, or not one it takes. The message names
 * the field, and the server answers it as JSON-RPC's invalid params (-32602).
 */
final class McpArgumentException extends RuntimeException
{
}
