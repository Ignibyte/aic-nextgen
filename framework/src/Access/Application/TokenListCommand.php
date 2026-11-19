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

use function implode;

/**
 * `bin/aic token:list` (#313): each bearer token's name, scopes and when it
 * was added, one a line; never a value, which is not kept.
 */
final readonly class TokenListCommand implements Command
{
    /**
     * @param BearerTokens $bearerTokens The tokens.
     */
    public function __construct(
        private BearerTokens $bearerTokens,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the tokens cannot be read.
     *
     * @return CommandResponse The tokens, or a line saying there are none.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['Usage: token:list.'], 2);
        }

        $lines = [];

        foreach ($this->bearerTokens->all() as $apiToken) {
            $lines[] = $apiToken->name . "\t" . implode(' ', $apiToken->scopes) . "\t" . $apiToken->createdAt;
        }

        return new CommandResult($lines === [] ? ['No tokens.'] : $lines, 0);
    }
}
