<?php

declare(strict_types=1);

namespace App\Note\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\Textarea;
use AIC\Form\Application\FormTokens;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The note forms, built from the atoms: the one that creates or edits a
 * note, and the one whose button deletes it. Each carries the visitor's CSRF
 * token (#42) and an envelope naming its note (#43).
 */
final readonly class NoteForms
{
    /**
     * @param NoteEnvelope $noteEnvelope Binds each form to its note.
     * @param FormTokens   $formTokens   Issues the visitor's CSRF token.
     */
    public function __construct(
        private NoteEnvelope $noteEnvelope,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $action        Where it posts.
     * @param string                 $title         The title to show in the field.
     * @param string                 $body          The body to show in the field.
     * @param string                 $error         The error to show; empty for none.
     *
     * @return Form The form.
     */
    public function form(
        ServerRequestInterface $serverRequest,
        string $action,
        string $title,
        string $body,
        string $error,
    ): Form {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'form-error')];
        $fields = [
            $this->noteEnvelope->field($action),
            ...$alerts,
            new FormField('Title', 'title', new Input('title', 'title', $title, $error !== '')),
            new FormField('Body', 'body', new Textarea('body', 'body', $body)),
            new Button('Save', 'submit', ''),
        ];

        return new Form('note-form', $action, $fields, $this->formTokens->issue($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param string                 $action        Where it posts.
     * @param PostedNote             $postedNote    What was posted, with its error.
     *
     * @throws JsonException When the envelope cannot be encoded.
     *
     * @return Form The form again, holding what was posted and showing the error.
     */
    public function again(ServerRequestInterface $serverRequest, string $action, PostedNote $postedNote): Form
    {
        return $this->form($serverRequest, $action, $postedNote->title, $postedNote->body, $postedNote->error);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $path          The note's path.
     *
     * @return Form The form whose button deletes the note.
     */
    public function deleteForm(ServerRequestInterface $serverRequest, string $path): Form
    {
        $fields = [$this->noteEnvelope->field($path), new Button('Delete', 'submit', 'delete')];

        return new Form('', $path . '/delete', $fields, $this->formTokens->issue($serverRequest));
    }
}
