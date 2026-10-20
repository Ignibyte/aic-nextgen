<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Link;
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
        $items = '';
        $count = 0;

        foreach ($this->noteRepository->all() as $note) {
            $items .= '<li>' . (new Link($note->title, '/notes/' . $note->id))->render()->markup() . '</li>';
            $count += 1;
        }

        $list = $items === ''
            ? '<p id="no-notes">No notes yet.</p>'
            : '<ul id="notes">' . $items . '</ul>';
        $heading = '<h2>Notes ' . (new Badge((string) $count, 'note-count'))->render()->markup() . '</h2>';

        return $this->noteViews->page(
            $heading . $list . '<h2>New note</h2>' . $this->noteViews->form($serverRequest->getUri()->getPath(), '', '', ''),
            200,
        );
    }
}
