<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * POST /jobs?mode=succeed|fail|broken: queues the demo job (#46) and goes back to
 * /jobs, naming the mode it queued (`?queued=succeed`), the Location gate:23
 * opens as `{location}` (#212). POST /jobs?mode=discard&id=N drops failed job
 * N (#262) and goes back with `?discarded=N`, or `?discarded=none` when there
 * was no such job.
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
     * @throws JsonException            When the payload cannot be encoded.
     * @throws QueryException           When the job cannot be queued.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return PageResponse 303 to /jobs with the queued mode.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();

        if (($query['mode'] ?? '') === 'discard') {
            return $this->discard(array_key_exists('id', $query) && is_string($query['id']) ? $query['id'] : '');
        }

        $mode = match ($query['mode'] ?? '') {
            'fail' => 'fail',
            'broken' => 'broken',
            default => 'succeed',
        };
        $this->postgresQueue->dispatch('demo', ['mode' => $mode], 0);

        return new PageRedirect('/jobs?queued=' . $mode);
    }

    /**
     * @param string $id The failed job's id, as posted.
     *
     * @throws QueryException           When the record cannot be changed.
     * @throws InvalidArgumentException When the redirect is invalid.
     *
     * @return PageResponse 303 to /jobs, naming the job discarded or `none`.
     */
    private function discard(string $id): PageResponse
    {
        $discarded = preg_match('/^[1-9][0-9]{0,17}$/', $id) === 1 && $this->postgresQueue->discard((int) $id);

        return new PageRedirect('/jobs?discarded=' . ($discarded ? $id : 'none'));
    }
}
