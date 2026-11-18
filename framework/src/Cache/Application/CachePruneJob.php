<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Database\Contracts\QueryException;
use Override;

/**
 * The framework's `aic-cache-prune` job (#312): what `cache:prune` does, for
 * a schedule an app declares in a manifest, every quarter hour or so.
 */
final readonly class CachePruneJob implements Job
{
    /**
     * The job's name in the registry.
     */
    public const string NAME = 'aic-cache-prune';

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
     * @param JobInput $jobInput No payload.
     *
     * @throws QueryException When a delete fails.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $this->postgresCache->prune();
        $this->rateLimiter->prune();

        return new JobDone();
    }
}
