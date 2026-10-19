<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Domain\PageDocument;

use function htmlspecialchars;

/**
 * The note pages' markup: the note form, and the documents around their
 * bodies, the 404 among them.
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
     * @param string $action Where it posts.
     * @param string $title  The title to show in the field.
     * @param string $body   The body to show in the field.
     * @param string $error  The error to show; empty for none.
     *
     * @return string The form markup.
     */
    public function form(string $action, string $title, string $body, string $error): string
    {
        $message = $error === ''
            ? ''
            : '<p id="form-error">' . htmlspecialchars($error) . '</p>';

        return '<form id="note-form" method="post" action="' . htmlspecialchars($action) . '">' . $message
            . '<p><label for="title">Title</label> <input id="title" name="title" value="' . htmlspecialchars($title) . '"></p>'
            . '<p><label for="body">Body</label> <textarea id="body" name="body">' . htmlspecialchars($body) . '</textarea></p>'
            . '<button type="submit">Save</button></form>';
    }

    /**
     * @param string $body   The page's body markup, already escaped.
     * @param int    $status 200, or 422 for a form shown again with its error.
     *
     * @return PageDocument The page.
     */
    public function page(string $body, int $status): PageDocument
    {
        return new PageDocument($this->htmlLayout->document($body . '<p><a href="/notes">All notes</a></p>'), $status);
    }

    /**
     * @return PageDocument The 404 page for a note that does not exist.
     */
    public function notFound(): PageDocument
    {
        return $this->page('<p id="not-found">Note not found.</p>', 404);
    }
}
