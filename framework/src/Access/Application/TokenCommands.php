<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;

/**
 * The three `token:*` commands (#313), by name, for the framework's
 * command list.
 */
final readonly class TokenCommands
{
    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> `token:add`, `token:list` and `token:revoke`.
     */
    public function byName(): array
    {
        $bearerTokens = new BearerTokens($this->database);

        return [
            'token:add' => new TokenAddCommand($bearerTokens),
            'token:list' => new TokenListCommand($bearerTokens),
            'token:revoke' => new TokenRevokeCommand($bearerTokens),
        ];
    }
}
