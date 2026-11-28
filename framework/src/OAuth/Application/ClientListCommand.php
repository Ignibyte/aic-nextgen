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

use function implode;

/**
 * `bin/aic client:list` (#331): each OAuth client, one a line: its id,
 * whether it is confidential or public, its scopes, its redirect URIs, when
 * it was added and how many of its access tokens are live (#352). Never a
 * secret.
 */
final readonly class ClientListCommand implements Command
{
    /**
     * @param OAuthClients $oauthClients The clients.
     */
    public function __construct(
        private OAuthClients $oauthClients,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the clients cannot be read.
     *
     * @return CommandResponse The clients, or a line saying there are none.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['Usage: client:list.'], 2);
        }

        $lines = [];

        foreach ($this->oauthClients->all() as $oauthClient) {
            $lines[] = $oauthClient->id . "\t" . ($oauthClient->confidential ? 'confidential' : 'public') . "\t"
                . implode(' ', $oauthClient->scopes) . "\t" . implode(' ', $oauthClient->redirectUris) . "\t"
                . $oauthClient->createdAt . "\t" . $oauthClient->liveTokens . ' live tokens';
        }

        return new CommandResult($lines === [] ? ['No clients.'] : $lines, 0);
    }
}
