<?php

declare(strict_types=1);

namespace App\Statement\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\QueueRecords;
use AIC\Queue\Domain\JobState;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET /statements/{id} (#336): where the statement's job stands, by the id
 * it was queued under, and its PDF once the worker wrote it. An id no job
 * holds and no file answers is no statement: 404.
 */
final readonly class StatementShowPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param QueueRecords  $queueRecords  Reads the job's state.
     * @param PrivateFiles  $privateFiles  Where the worker keeps the PDF.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private QueueRecords $queueRecords,
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the job's `id`.
     *
     * @throws InvalidArgumentException When the key or the page layout is invalid.
     * @throws QueryException           When the queue cannot be read.
     * @throws RouteNotFoundException   When no job and no file is the statement, which AIC answers 404.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $id = $serverRequest->getAttribute('id');

        if (!is_string($id)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        $jobState = $this->queueRecords->state((int) $id);
        $written = $this->privateFiles->exists('statements/' . $id . '.pdf');

        if ($jobState === JobState::Done && !$written) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        $components = [new Heading('Statement'), new Paragraph('Its job is ' . $jobState->value . '.', 'statement-state')];

        if ($written) {
            $components[] = new Link('Download the PDF', '/statements/' . $id . '/pdf');
        }

        return $this->pageDocuments->content($components, 200, 'Statement');
    }
}
