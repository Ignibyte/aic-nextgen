<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\NoteRepository;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /notes/{id}/delete: deletes the note and answers 303 to the list; 404
 * when there is no such note.
 */
final readonly class NoteDeletePost implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteRequest    $noteRequest    Reads the note's id.
     * @param NoteViews      $noteViews      The note pages' markup.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `id` attribute.
     *
     * @throws QueryException When the note cannot be deleted.
     *
     * @return PageResponse The redirect, or the 404 page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        try {
            $this->noteRepository->delete($this->noteRequest->noteId($serverRequest));
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        return new PageRedirect('/notes');
    }
}
