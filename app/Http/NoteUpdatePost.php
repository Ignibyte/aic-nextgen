<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\NoteRepository;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /notes/{id}: a valid title saves the note and answers 303 to its page;
 * an invalid one shows the form again, with its error, as 422; 404 when there
 * is no such note.
 */
final readonly class NoteUpdatePost implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteRequest    $noteRequest    Reads the note's id and the posted note.
     * @param NoteViews      $noteViews      The note pages' documents.
     * @param NoteForms      $noteForms      The note forms.
     * @param NoteEnvelope   $noteEnvelope   Checks the form was issued for this note.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
        private NoteForms $noteForms,
        private NoteEnvelope $noteEnvelope,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with the `id` attribute.
     *
     * @throws QueryException When the note cannot be stored.
     *
     * @return PageResponse The redirect, the form again as 422, the 404 page, or 400 for an altered form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteId = $this->noteRequest->noteId($serverRequest);
        $path = '/notes/' . $noteId;
        $postedNote = $this->noteRequest->posted($serverRequest);

        if (!$this->noteEnvelope->matches($serverRequest, $path)) {
            return $this->noteViews->altered();
        }

        if ($postedNote->error !== '') {
            return $this->noteViews->page([new Html('<h2>Edit</h2>'), $this->noteForms->again($serverRequest, $path, $postedNote)], 422);
        }

        try {
            $this->noteRepository->update($noteId, $postedNote->title, $postedNote->body);
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        return new PageRedirect($path);
    }
}
