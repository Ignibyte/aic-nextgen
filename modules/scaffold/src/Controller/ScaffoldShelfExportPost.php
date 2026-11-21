<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Repository\ScaffoldShelfExportRepository;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /scaffold/exports, made by bin/aic make:export (#323): stores an export and
 * queues the job `scaffold-shelf-export` that writes it, then answers 303 to its
 * download, which is 404 until the job has run.
 */
final readonly class ScaffoldShelfExportPost implements Page
{
    /**
     * @param ScaffoldShelfExportRepository $scaffoldShelfExportRepository The exports.
     */
    public function __construct(
        private ScaffoldShelfExportRepository $scaffoldShelfExportRepository,
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
        return new PageRedirect('/scaffold/exports/' . $this->scaffoldShelfExportRepository->queue());
    }
}
