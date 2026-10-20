<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Textarea;
use AIC\Controller\Domain\PageDocument;

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
        $alert = $error === ''
            ? new Html('')
            : (new Alert(AlertSeverity::Error, $error, 'form-error'))->render();
        $html = $alert
            ->append((new FormField('Title', 'title', new Input('title', 'title', $title)))->render())
            ->append((new FormField('Body', 'body', new Textarea('body', 'body', $body)))->render())
            ->append((new Button('Save', 'submit', ''))->render());

        return (new Form('note-form', $action, $html))->render()->markup();
    }

    /**
     * @param string $path The note's path.
     *
     * @return string The form whose button deletes the note.
     */
    public function deleteForm(string $path): string
    {
        return (new Form('', $path . '/delete', (new Button('Delete', 'submit', 'delete'))->render()))->render()->markup();
    }

    /**
     * @param string $body   The page's body markup, already escaped.
     * @param int    $status 200, or 422 for a form shown again with its error.
     *
     * @return PageDocument The page.
     */
    public function page(string $body, int $status): PageDocument
    {
        $allNotes = (new Link('All notes', '/notes'))->render()->markup();

        return new PageDocument($this->htmlLayout->document($body . '<p>' . $allNotes . '</p>'), $status);
    }

    /**
     * @return PageDocument The 404 page for a note that does not exist.
     */
    public function notFound(): PageDocument
    {
        return $this->page('<p id="not-found">Note not found.</p>', 404);
    }
}
