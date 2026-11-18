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
 * `bin/aic cache:prune` (#312): removes the expired cache entries and the
 * closed rate windows. The job `aic-cache-prune` does the same on a schedule
 * an app declares.
 */
final readonly class CachePruneCommand implements Command
{
    /**
     * @param PostgresCache $postgresCache The cache.
     * @param RateLimiter   $rateLimiter   The rate counts.
     */
    public function __construct(
        private PostgresCache $postgresCache,
        private RateLimiter $rateLimiter,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws QueryException           When a delete fails.
     * @throws InvalidArgumentException When the result is invalid.
     *
     * @return CommandResponse 0, with what it removed; 2 for a usage error.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if ($commandInput->argument(0, '') !== '') {
            return new CommandResult(['usage: bin/aic cache:prune'], 2);
        }

        $entries = $this->postgresCache->prune();
        $windows = $this->rateLimiter->prune();

        return new CommandResult(['cache:prune removed ' . $entries . ' cache entries and ' . $windows . ' rate windows.'], 0);
    }
}
