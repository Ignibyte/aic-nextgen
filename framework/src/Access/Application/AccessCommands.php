<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\OAuth\Application\ClientCommands;

/**
 * Every command of the access system, by name, for the framework's command
 * list: the four `role:*` (#304) and the three `token:*` (#313).
 */
final readonly class AccessCommands
{
    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> The role, token and OAuth client (#331) commands.
     */
    public function byName(): array
    {
        return [
            ...(new RoleCommands($this->database))->byName(),
            ...(new TokenCommands($this->database))->byName(),
            ...(new ClientCommands($this->database))->byName(),
        ];
    }
}
