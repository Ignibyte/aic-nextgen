<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\Link;
use AIC\Controller\Domain\PageDocument;
use App\Site\Service\HtmlLayout;
use InvalidArgumentException;

/**
 * The note pages' documents: their content and the All notes link on
 * AppLayout, the 404 among them. The forms are NoteForms'.
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
     * @throws InvalidArgumentException Never: 400 is a document status.
     *
     * @return PageDocument The 400 page for a note form whose envelope was altered or names another note.
     */
    public function altered(): PageDocument
    {
        return $this->page([new Html('<p id="altered">This form was changed after it was sent. Reload the note and try again.</p>')], 400);
    }

    /**
     * @return PageDocument The 404 page for a note that does not exist.
     */
    public function notFound(): PageDocument
    {
        return $this->page([new Html('<p id="not-found">Note not found.</p>')], 404);
    }
}
