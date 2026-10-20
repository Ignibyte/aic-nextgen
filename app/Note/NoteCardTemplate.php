<?php

declare(strict_types=1);

namespace App\Note;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;

/**
 * NoteCard's markup, compiled from NoteCard.tpl by bin/aic-build (#36):
 * edit the template and run composer run build, never this file.
 */
final readonly class NoteCardTemplate
{
    use RendersMarkup;

    /**
     * @param NoteCard $noteCard The component.
     *
     * @return Html Its markup.
     */
    public function render(NoteCard $noteCard): Html
    {
        return new Html($this->part0($noteCard));
    }

    /**
     * @param NoteCard $noteCard The component.
     *
     * @return string Markup from NoteCard.tpl.
     */
    private function part0(NoteCard $noteCard): string
    {
        return '<article id="note"><h2 id="note-title">'
            . self::text($noteCard->note->title)
            . "</h2>\n"
            . '<p id="note-body">'
            . self::text($noteCard->note->body)
            . "</p>\n"
            . '<p>Written <time>'
            . self::text($noteCard->note->createdAt)
            . "</time></p></article>\n";
    }
}
