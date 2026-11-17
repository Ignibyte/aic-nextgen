<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\RoleChange;
use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;

/**
 * The four `role:*` commands (#304), by name, for the framework's command
 * list.
 */
final readonly class RoleCommands
{
    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> `role:assign`, `role:grant`, `role:revoke` and `role:unassign`.
     */
    public function byName(): array
    {
        $postgresRoles = new PostgresRoles($this->database);
        $commands = [];

        foreach ([RoleChange::Assign, RoleChange::Grant, RoleChange::Revoke, RoleChange::Unassign] as $roleChange) {
            $commands['role:' . $roleChange->value] = new RoleCommand($roleChange, $postgresRoles);
        }

        return $commands;
    }
}
