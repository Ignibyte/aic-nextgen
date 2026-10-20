<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Textarea;
use AIC\Controller\Domain\PageDocument;
use InvalidArgumentException;

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
     * @return Html The form.
     */
    public function form(string $action, string $title, string $body, string $error): Html
    {
        $alert = $error === ''
            ? new Html('')
            : (new Alert(AlertSeverity::Error, $error, 'form-error'))->render();
        $html = $alert
            ->append((new FormField('Title', 'title', new Input('title', 'title', $title)))->render())
            ->append((new FormField('Body', 'body', new Textarea('body', 'body', $body)))->render())
            ->append((new Button('Save', 'submit', ''))->render());

        return (new Form('note-form', $action, $html))->render();
    }

    /**
     * @param string $path The note's path.
     *
     * @return Html The form whose button deletes the note.
     */
    public function deleteForm(string $path): Html
    {
        return (new Form('', $path . '/delete', (new Button('Delete', 'submit', 'delete'))->render()))->render();
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
     * @return PageDocument The 404 page for a note that does not exist.
     */
    public function notFound(): PageDocument
    {
        return $this->page([new Html('<p id="not-found">Note not found.</p>')], 404);
    }
}
