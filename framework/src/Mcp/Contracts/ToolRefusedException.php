<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

use RuntimeException;

/**
 * A tool refuses a call it understood (#316): the account is frozen, the amount
 * cannot be split. The server answers it as the tool's result with `isError`,
 * its message as the text the agent reads, so the agent can correct itself.
 */
final class ToolRefusedException extends RuntimeException
{
}
