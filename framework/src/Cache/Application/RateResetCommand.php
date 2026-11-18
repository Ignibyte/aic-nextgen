<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic rate:reset <name>` (#312): clears every client's count on one
 * rate limit, so the next hit opens a new window.
 */
final readonly class RateResetCommand implements Command
{
    /**
     * @param RateLimiter $rateLimiter The rate counts.
     */
    public function __construct(
        private RateLimiter $rateLimiter,
    ) {
    }

    /**
     * @param CommandInput $commandInput The limit's name.
     *
     * @throws QueryException           When the delete fails.
     * @throws InvalidArgumentException When the result is invalid.
     *
     * @return CommandResponse 0, with how many counts it cleared; 2 for a usage error or a malformed name.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $name = $commandInput->argument(0, '');

        if ($name === '' || $commandInput->argument(1, '') !== '') {
            return new CommandResult(['usage: bin/aic rate:reset <name>'], 2);
        }

        try {
            $cleared = $this->rateLimiter->reset($name);
        } catch (InvalidArgumentException) {
            $cleared = -1;
        }

        return $cleared < 0
            ? new CommandResult(['usage: bin/aic rate:reset <name>'], 2)
            : new CommandResult(['rate:reset cleared ' . $cleared . ' count(s) on ' . $name . '.'], 0);
    }
}
