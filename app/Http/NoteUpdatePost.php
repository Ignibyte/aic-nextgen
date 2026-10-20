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
     * @param NoteViews      $noteViews      The note pages' markup.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteRequest $noteRequest,
        private NoteViews $noteViews,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with the `id` attribute.
     *
     * @throws QueryException When the note cannot be stored.
     *
     * @return PageResponse The redirect, the form again as 422, or the 404 page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteId = $this->noteRequest->noteId($serverRequest);
        $path = '/notes/' . $noteId;
        $postedNote = $this->noteRequest->posted($serverRequest);

        if ($postedNote->error !== '') {
            return $this->noteViews->page(
                [new Html('<h2>Edit</h2>'), $this->noteViews->form($path, $postedNote->title, $postedNote->body, $postedNote->error)],
                422,
            );
        }

        try {
            $this->noteRepository->update($noteId, $postedNote->title, $postedNote->body);
        } catch (EntityNotFoundException) {
            return $this->noteViews->notFound();
        }

        return new PageRedirect($path);
    }
}
