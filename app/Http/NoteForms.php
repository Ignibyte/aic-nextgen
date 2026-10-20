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
use AIC\Component\Domain\Textarea;
use AIC\Form\Application\FormTokens;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The note forms, built from the atoms: the one that creates or edits a
 * note, and the one whose button deletes it. Each carries the visitor's CSRF
 * token (#42).
 */
final readonly class NoteForms
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $action        Where it posts.
     * @param string                 $title         The title to show in the field.
     * @param string                 $body          The body to show in the field.
     * @param string                 $error         The error to show; empty for none.
     *
     * @return Html The form.
     */
    public function form(
        ServerRequestInterface $serverRequest,
        string $action,
        string $title,
        string $body,
        string $error,
    ): Html {
        $alert = $error === ''
            ? new Html('')
            : (new Alert(AlertSeverity::Error, $error, 'form-error'))->render();
        $html = $alert
            ->append((new FormField('Title', 'title', new Input('title', 'title', $title)))->render())
            ->append((new FormField('Body', 'body', new Textarea('body', 'body', $body)))->render())
            ->append((new Button('Save', 'submit', ''))->render());

        return (new Form('note-form', $action, $html, $this->formTokens->issue($serverRequest)))->render();
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The note's path.
     *
     * @return Html The form whose button deletes the note.
     */
    public function deleteForm(ServerRequestInterface $serverRequest, string $path): Html
    {
        $html = (new Button('Delete', 'submit', 'delete'))->render();

        return (new Form('', $path . '/delete', $html, $this->formTokens->issue($serverRequest)))->render();
    }
}
