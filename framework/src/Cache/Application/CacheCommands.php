<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;

/**
 * The cache's commands (#312), as `FrameworkCommands` lists them:
 * `cache:prune` and `rate:reset`.
 */
final readonly class CacheCommands
{
    /**
     * @param Database $database The database, with the cache tables.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> The commands by name.
     */
    public function byName(): array
    {
        $rateLimiter = new RateLimiter($this->database);

        return [
            'cache:prune' => new CachePruneCommand(new PostgresCache($this->database), $rateLimiter),
            'rate:reset' => new RateResetCommand($rateLimiter),
        ];
    }
}
