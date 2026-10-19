<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\NoteRepository;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;

/**
 * GET /notes/{id}: the note, the form that edits it and the button that
 * deletes it; 404 when there is no such note.
 */
final readonly class NoteShowPage implements Page
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
     * @throws QueryException When the note cannot be read.
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
            '<article id="note"><h2 id="note-title">' . htmlspecialchars($note->title) . '</h2>'
            . '<p id="note-body">' . htmlspecialchars($note->body) . '</p>'
            . '<p>Written <time>' . htmlspecialchars($note->createdAt) . '</time></p></article>'
            . '<h2>Edit</h2>' . $this->noteViews->form($path, $note->title, $note->body, '')
            . '<form method="post" action="' . $path . '/delete"><button id="delete" type="submit">Delete</button></form>',
            200,
        );
    }
}
