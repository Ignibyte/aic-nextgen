<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Access\Application\Actors;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Form\NoteEnvelope;
use App\Note\Policy\NotePolicy;
use App\Note\Repository\NoteRepository;
use App\Note\Service\NoteRequest;
use App\Note\Service\NoteViews;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /notes/{id}/delete: deletes the note and answers 303 to the list; 404
 * when there is no such note, 403 when its policy refuses the actor (#304).
 */
final readonly class NoteDeletePost implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteRequest    $noteRequest    Reads the note's id.
     * @param NoteViews      $noteViews      The note pages' documents.
     * @param NoteEnvelope   $noteEnvelope   Checks the form was issued for this note.
     * @param NotePolicy     $notePolicy     Who may delete it.
     * @param Actors         $actors         The request's actor.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
        private NoteEnvelope $noteEnvelope,
        private NotePolicy $notePolicy,
        private Actors $actors,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `id` attribute.
     *
     * @throws QueryException           When the note cannot be read or deleted.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     * @throws EntityNotFoundException  When the note went between its read and its delete.
     *
     * @return PageResponse The redirect, the 404 page, 403 when the policy refuses, or 400 for an altered form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteId = $this->noteRequest->noteId($serverRequest);

        if (!$this->noteEnvelope->matches($serverRequest, '/notes/' . $noteId)) {
            return $this->noteViews->altered();
        }

        try {
            $note = $this->noteRepository->find($noteId);
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        if (!$this->notePolicy->delete($this->actors->actor($serverRequest), $note)->allows()) {
            return $this->noteViews->forbidden();
        }

        $this->noteRepository->delete($noteId);

        return new PageRedirect('/notes');
    }
}
