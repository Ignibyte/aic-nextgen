<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Contracts\Html;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\NoteForms;
use App\Note\Repository\NoteRepository;
use App\Note\Service\NoteRequest;
use App\Note\Service\NoteViews;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /notes: a valid title creates the note and answers 303 to its page; an
 * invalid one shows the form again, with its error, as 422.
 */
final readonly class NoteCreatePost implements Page
{
    /**
     * @param NoteRepository $noteRepository The notes.
     * @param NoteRequest    $noteRequest    Reads the posted note.
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
     * @param ServerRequestInterface $serverRequest The posted form.
     *
     * @throws QueryException When the note cannot be stored.
     *
     * @return PageResponse The redirect, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $postedNote = $this->noteRequest->posted($serverRequest);

        if ($postedNote->error !== '') {
            $form = $this->noteForms->again($serverRequest, '/notes', $postedNote);

            return $this->noteViews->page([new Html('<h2>New note</h2>'), $form], 422);
        }

        return new PageRedirect('/notes/' . $this->noteRepository->create($postedNote->title, $postedNote->body));
    }
}
