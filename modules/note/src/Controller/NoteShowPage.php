<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Form\NoteForms;
use App\Note\Repository\NoteRepository;
use App\Note\Service\NoteRequest;
use App\Note\Service\NoteViews;
use App\Theme\Components\NoteCard\NoteCard;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /notes/{id}: the note, the form that edits it and the button that
 * deletes it; 404 when there is no such note.
 */
final readonly class NoteShowPage implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteRequest    $noteRequest    Reads the note's id.
     * @param NoteViews      $noteViews      The note pages' documents.
     * @param NoteForms      $noteForms      The note forms.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
        private NoteForms $noteForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `id` attribute.
     *
     * @throws QueryException When the note cannot be read.
     * @throws JsonException  When a value cannot be encoded as JSON.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The note, or the 404 page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        try {
            $note = $this->noteRepository->find($this->noteRequest->noteId($serverRequest));
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        $path = '/notes/' . $note->id;

        return $this->noteViews->page(
            [
                new NoteCard($note->title, $note->body, $note->createdAt),
                new Heading('Edit'),
                $this->noteForms->form($serverRequest, $path, $note->title, $note->body, ''),
                $this->noteForms->deleteForm($serverRequest, $path),
            ],
            200,
        );
    }
}
