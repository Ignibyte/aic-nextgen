<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;

/**
 * The three `client:*` commands (#331), by name, for the framework's
 * command list.
 */
final readonly class ClientCommands
{
    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> `client:add`, `client:list` and `client:revoke`.
     */
    public function byName(): array
    {
        $oauthClients = new OAuthClients($this->database);

        return [
            'client:add' => new ClientAddCommand($oauthClients),
            'client:list' => new ClientListCommand($oauthClients),
            'client:revoke' => new ClientRevokeCommand($oauthClients),
        ];
    }
}
