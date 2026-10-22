<?php

declare(strict_types=1);

namespace App\Note\Component;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\RendersMarkup;

/**
 * NoteList's markup, compiled from NoteList.html.php by bin/aic-build (#97):
 * edit the template and run composer run build, never this file.
 */
final readonly class NoteListTemplate
{
    use RendersMarkup;

    /**
     * @param NoteList $noteList The component.
     *
     * @return Html Its markup.
     */
    public function render(NoteList $noteList): Html
    {
        return new Html($this->part4($noteList));
    }

    /**
     * @return string Markup from NoteList.html.php.
     */
    private function part0(): string
    {
        return "<p id=\"no-notes\">No notes yet.</p>\n";
    }

    /**
     * @param KeyedComponent $keyedComponent A loop's variable.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part1(KeyedComponent $keyedComponent): string
    {
        return '<li>'
            . '<!--t:c NoteList.2:'
            . self::text($keyedComponent->key())
            . '-->'
            . $keyedComponent->render()->markup()
            . '<!--/t:c-->'
            . "</li>\n";
    }

    /**
     * @param NoteList $noteList The component.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part2(NoteList $noteList): string
    {
        $markup = '';

        foreach ($noteList->links as $keyedComponent) {
            $markup .= $this->part1($keyedComponent);
        }

        return $markup;
    }

    /**
     * @param NoteList $noteList The component.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part3(NoteList $noteList): string
    {
        return "<ul id=\"notes\">\n"
            . '<!--t:b-->'
            . $this->part2($noteList)
            . '<!--/t:b-->'
            . "</ul>\n";
    }

    /**
     * @param NoteList $noteList The component.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part4(NoteList $noteList): string
    {
        return '<h2>Notes '
            . '<!--t:c NoteList.1-->'
            . $noteList->count->render()->markup()
            . '<!--/t:c-->'
            . "</h2>\n"
            . '<!--t:b-->'
            . ($noteList->links === []
                ? $this->part0()
                : $this->part3($noteList))
            . '<!--/t:b-->';
    }
}
