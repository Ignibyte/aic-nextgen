<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match;
use function strlen;

/**
 * The tags page's form (#190): a note's number and a tag, and what is wrong
 * with a post of them.
 */
final readonly class NoteTagForms
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $noteId        The note's number to show.
     * @param string                 $tag           The tag to show.
     * @param string                 $error         Why the last post was refused; empty for none.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, string $noteId, string $tag, string $error): Form
    {
        $token = $this->formTokens->issue($serverRequest)->value();

        return new Form('tag-form', '/notes/tags', [
            ...self::alerts($error),
            new FormField('Note number', 'note_id', new Input('note_id', 'note_id', $noteId)),
            new FormField('Tag', 'tag', new Input('tag', 'tag', $tag)),
            new Button('Add the tag', 'submit', 'add-tag'),
        ], $token);
    }

    /**
     * @param string $noteId A note's number as posted.
     * @param string $tag    A tag as posted.
     *
     * @return string What is wrong with them, for a person; empty when nothing is.
     */
    public function problem(string $noteId, string $tag): string
    {
        return match (true) {
            preg_match('/^[1-9]\\d{0,17}$/', $noteId) !== 1 => "Give the note's number.",
            !self::isTag($tag) => 'Give a tag of lower case letters, digits and hyphens, up to 50.',
            default => '',
        };
    }

    /**
     * @param string $tag A tag as posted.
     *
     * @return bool Whether it is lower case letters, digits and inner hyphens, up to 50.
     */
    private static function isTag(string $tag): bool
    {
        return strlen($tag) <= 50 && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $tag) === 1;
    }

    /**
     * @param string $error Why the last post was refused; empty for none.
     *
     * @return list<Alert> The error above the fields; none when there is none.
     */
    private static function alerts(string $error): array
    {
        return $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'tag-error')];
    }
}
