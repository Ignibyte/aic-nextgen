<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Note\NoteRepository;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /notes: every note, oldest first, each linking to its page, and the form
 * that creates one.
 */
final readonly class NoteListPage implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteViews      $noteViews      The note pages' markup.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteViews $noteViews,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException When the notes cannot be read.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->noteViews->page(
            [
                new NoteList($this->noteRepository->all()),
                new Html('<h2>New note</h2>'),
                $this->noteViews->form($serverRequest->getUri()->getPath(), '', '', ''),
            ],
            200,
        );
    }
}
