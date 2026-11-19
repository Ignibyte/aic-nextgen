<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function preg_match;

/**
 * `bin/aic token:revoke <name>` (#313): the token stops working on the next
 * request. Exit 0 when it was there, 1 when it was not, 2 for a line it
 * does not take.
 */
final readonly class TokenRevokeCommand implements Command
{
    /**
     * @param BearerTokens $bearerTokens The tokens.
     */
    public function __construct(
        private BearerTokens $bearerTokens,
    ) {
    }

    /**
     * @param CommandInput $commandInput The token's name.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the token cannot be removed.
     *
     * @return CommandResponse Whether it was revoked.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $name = $commandInput->argument(0, '');

        if (preg_match(TokenAddCommand::NAME, $name) !== 1 || $commandInput->argument(1, '') !== '') {
            return new CommandResult(['Usage: token:revoke <name>.'], 2);
        }

        $revoked = $this->bearerTokens->revoke($name);

        return new CommandResult(['token:revoke ' . $name . ($revoked ? ': done.' : ': no such token.')], $revoked ? 0 : 1);
    }
}
