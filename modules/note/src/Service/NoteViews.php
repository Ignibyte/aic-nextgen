<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Domain\PageDocument;
use App\Note\Component\NoteList;
use App\Note\Entity\Note;
use App\Site\Service\HtmlLayout;
use InvalidArgumentException;

use function count;

/**
 * The note pages' documents: their content and the All notes link on
 * AppLayout, the 404 among them, and the list of notes. The forms are
 * NoteForms'.
 */
final readonly class NoteViews
{
    /**
     * @param HtmlLayout $htmlLayout The document around each page.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
    ) {
    }

    /**
     * @param list<Component> $content The `content` region's components.
     * @param int             $status  200, 404, or 422 for a form shown again with its error.
     *
     * @throws InvalidArgumentException When the status is none of these.
     *
     * @return PageDocument The page: its content, and the All notes link in `nav`.
     */
    public function page(array $content, int $status): PageDocument
    {
        return $this->htmlLayout->page(
            new Regions([new Region('content', $content), new Region('nav', [new Link('All notes', '/notes')])]),
            $status,
        );
    }

    /**
     * @param list<Note> $notes The notes, in order.
     *
     * @return NoteList Their count as a badge and a link to each note's page, keyed by its id.
     */
    public function noteList(array $notes): NoteList
    {
        $links = [];

        foreach ($notes as $note) {
            $links[] = new KeyedComponent((string) $note->id, new Link($note->title, '/notes/' . $note->id));
        }

        return new NoteList(new Badge((string) count($notes), 'note-count'), $links);
    }

    /**
     * @throws InvalidArgumentException Never: 400 is a document status.
     *
     * @return PageDocument The 400 page for a note form whose envelope was altered or names another note.
     */
    public function altered(): PageDocument
    {
        return $this->page([new Paragraph('This form was changed after it was sent. Reload the note and try again.', 'altered')], 400);
    }

    /**
     * @return PageDocument The 404 page for a note that does not exist.
     */
    public function notFound(): PageDocument
    {
        return $this->page([new Paragraph('Note not found.', 'not-found')], 404);
    }
}
