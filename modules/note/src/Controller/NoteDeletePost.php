<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Form\NoteEnvelope;
use App\Note\Repository\NoteRepository;
use App\Note\Service\NoteRequest;
use App\Note\Service\NoteViews;
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
     * @param NoteViews      $noteViews      The note pages' documents.
     * @param NoteEnvelope   $noteEnvelope   Checks the form was issued for this note.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
        private NoteEnvelope $noteEnvelope,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `id` attribute.
     *
     * @throws QueryException When the note cannot be deleted.
     *
     * @return PageResponse The redirect, the 404 page, or 400 for an altered form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteId = $this->noteRequest->noteId($serverRequest);

        if (!$this->noteEnvelope->matches($serverRequest, '/notes/' . $noteId)) {
            return $this->noteViews->altered();
        }

        try {
            $this->noteRepository->delete($noteId);
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        return new PageRedirect('/notes');
    }
}
