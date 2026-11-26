<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;

/**
 * The MCP system's commands (#332), by name, for the framework's command
 * list: `mcp:calls`, the capability registry's audit.
 */
final readonly class McpCommands
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> Each command, by name.
     */
    public function byName(): array
    {
        return [
            'mcp:calls' => new McpCallsCommand(new McpCallLog($this->database)),
        ];
    }
}
