<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function preg_match;

/**
 * `bin/aic client:revoke <id>` (#331): removes an OAuth client, and with it
 * every code and access token it holds. Exit 0 when removed, 1 when there is
 * no such client, 2 for a line it does not take.
 */
final readonly class ClientRevokeCommand implements Command
{
    /**
     * @param OAuthClients $oauthClients The clients.
     */
    public function __construct(
        private OAuthClients $oauthClients,
    ) {
    }

    /**
     * @param CommandInput $commandInput The client's id.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the rows cannot be removed.
     *
     * @return CommandResponse Whether it was removed.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $clientId = $commandInput->argument(0, '');

        if (preg_match(ClientAddCommand::ID, $clientId) !== 1 || $commandInput->argument(1, '') !== '') {
            return new CommandResult(['Usage: client:revoke <id>.'], 2);
        }

        $revoked = $this->oauthClients->revoke($clientId);

        return new CommandResult(['client:revoke ' . $clientId . ($revoked ? ': done.' : ': no such client.')], $revoked ? 0 : 1);
    }
}
