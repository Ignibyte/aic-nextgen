<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\ApiAccepted;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use {namespace}\Service\{payload};
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST {path}, made by bin/aic make:webhook (#321): a signed
 * delivery. The framework checked its signature against config/app.php's
 * webhook at that path before it got here. It queues the job
 * `{job}` with the body and answers 202 at once, so the sender
 * never waits on the work.
 */
final readonly class {class} implements Api
{
    /**
     * @param PostgresQueue $postgresQueue Queues the job.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The delivery.
     *
     * @throws JsonException  When the payload cannot be encoded.
     * @throws QueryException When the job cannot be stored.
     *
     * @return ApiResponse 202.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        ${variable} = new {payload}((string) $serverRequest->getBody());
        $this->postgresQueue->dispatch('{job}', ${variable}->values(), 0);

        return new ApiAccepted();
    }
}
