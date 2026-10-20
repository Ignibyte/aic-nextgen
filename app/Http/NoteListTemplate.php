<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Link;
use App\Note\Note;

use function array_map;
use function implode;

/**
 * NoteList's markup, compiled from NoteList.tpl by bin/aic-build (#36):
 * edit the template and run composer run build, never this file.
 */
final readonly class NoteListTemplate
{
    /**
     * @param NoteList $noteList The component.
     *
     * @return Html Its markup.
     */
    public function render(NoteList $noteList): Html
    {
        return new Html($this->part3($noteList));
    }

    /**
     * @return string Markup from NoteList.tpl.
     */
    private function part0(): string
    {
        return "<p id=\"no-notes\">No notes yet.</p>\n";
    }

    /**
     * @param Note $note A loop's item.
     *
     * @return string Markup from NoteList.tpl.
     */
    private function part1(Note $note): string
    {
        return '<li>'
            . (new Link($note->title, '/notes/' . $note->id))->render()->markup()
            . "</li>\n";
    }

    /**
     * @param NoteList $noteList The component.
     *
     * @return string Markup from NoteList.tpl.
     */
    private function part2(NoteList $noteList): string
    {
        return "<ul id=\"notes\">\n"
            . implode('', array_map(
                $this->part1(...),
                $noteList->notes,
            ))
            . "</ul>\n";
    }

    /**
     * @param NoteList $noteList The component.
     *
     * @return string Markup from NoteList.tpl.
     */
    private function part3(NoteList $noteList): string
    {
        return '<h2>Notes '
            . (new Badge($noteList->size(), 'note-count'))->render()->markup()
            . "</h2>\n"
            . ($noteList->isEmpty()
                ? $this->part0()
                : $this->part2($noteList));
    }
}
