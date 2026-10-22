<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\NoteForms;
use App\Note\Repository\NoteRepository;
use App\Note\Service\NoteViews;
use JsonException;
use LogicException;
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
     * @param NoteViews      $noteViews      The note pages' documents.
     * @param NoteForms      $noteForms      The note forms.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteViews $noteViews,
        private NoteForms $noteForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException When the notes cannot be read.
     * @throws JsonException  When a value cannot be encoded as JSON.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->noteViews->page(
            [
                $this->noteViews->noteList($this->noteRepository->all()),
                new Heading('New note'),
                $this->noteForms->form($serverRequest, $serverRequest->getUri()->getPath(), '', '', ''),
            ],
            200,
        );
    }
}
