<?php

declare(strict_types=1);

namespace App\Job;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /jobs?mode=succeed|fail: queues the demo job (#46) and goes back to
 * /jobs.
 */
final readonly class JobsPost implements Page
{
    /**
     * @param PostgresQueue $postgresQueue The queue.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with `?mode=`.
     *
     * @throws JsonException  When the payload cannot be encoded.
     * @throws QueryException When the job cannot be queued.
     *
     * @return PageResponse 303 to /jobs.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $mode = ($query['mode'] ?? '') === 'fail'
            ? 'fail'
            : 'succeed';
        $this->postgresQueue->dispatch('demo', ['mode' => $mode], 0);

        return new PageRedirect('/jobs');
    }
}
