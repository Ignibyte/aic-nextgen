<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use {namespace}\Repository\{repository};
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST {path}, made by bin/aic make:export (#323): stores an export and
 * queues the job `{job}` that writes it, then answers 303 to its
 * download, which is 404 until the job has run.
 */
final readonly class {class} implements Page
{
    /**
     * @param {repository} ${repositoryVariable} The exports.
     */
    public function __construct(
        private {repository} ${repositoryVariable},
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the form guard checked its token.
     *
     * @throws InvalidArgumentException When the table's type or the location is invalid.
     * @throws JsonException            When the job's payload cannot be encoded.
     * @throws QueryException           When the export cannot be stored or its job queued.
     *
     * @return PageResponse 303 to the export's download.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return new PageRedirect('{path}/' . $this->{repositoryVariable}->queue());
    }
}
