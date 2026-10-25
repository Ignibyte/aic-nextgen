<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Theme\Components\NoteCard\NoteCard;

/**
 * NoteCard's markup, compiled from
 * themes/app/components/NoteCard/note-card.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class NoteCardTemplate
{
    use RendersMarkup;

    /**
     * @param NoteCard $noteCard The component.
     *
     * @return string Its markup.
     */
    public function render(NoteCard $noteCard): string
    {
        return $this->part0($noteCard);
    }

    /**
     * @param NoteCard $noteCard The component.
     *
     * @return string Markup from themes/app/components/NoteCard/note-card.tpl.php.
     */
    private function part0(NoteCard $noteCard): string
    {
        return '<article id="note"><h2 id="note-title">'
            . self::text($noteCard->title)
            . "</h2>\n"
            . '<p id="note-body">'
            . self::text($noteCard->body)
            . "</p>\n"
            . '<p>Written <time>'
            . self::text($noteCard->createdAt)
            . "</time></p></article>\n";
    }
}
