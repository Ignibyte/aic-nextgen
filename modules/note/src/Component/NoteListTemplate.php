<?php

declare(strict_types=1);

namespace App\Note\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Exception\UnknownComponent;

/**
 * NoteList's markup, compiled from NoteList.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class NoteListTemplate
{
    /**
     * @param NoteList   $noteList   The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(NoteList $noteList, RenderPass $renderPass): string
    {
        return $this->part4($noteList, $renderPass);
    }

    /**
     * @return string Markup from NoteList.html.php.
     */
    private function part0(): string
    {
        return "<p id=\"no-notes\">No notes yet.</p>\n";
    }

    /**
     * @param RenderPass     $renderPass     The pass that writes its children.
     * @param KeyedComponent $keyedComponent A loop's variable.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part1(RenderPass $renderPass, KeyedComponent $keyedComponent): string
    {
        return '<li>'
            . $renderPass->child($keyedComponent, 'NoteList.2:' . $keyedComponent->key())
            . "</li>\n";
    }

    /**
     * @param NoteList   $noteList   The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part2(NoteList $noteList, RenderPass $renderPass): string
    {
        $markup = '';

        foreach ($noteList->links as $keyedComponent) {
            $markup .= $this->part1($renderPass, $keyedComponent);
        }

        return $markup;
    }

    /**
     * @param NoteList   $noteList   The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part3(NoteList $noteList, RenderPass $renderPass): string
    {
        return "<ul id=\"notes\">\n"
            . '<!--t:b-->'
            . $this->part2($noteList, $renderPass)
            . '<!--/t:b-->'
            . "</ul>\n";
    }

    /**
     * @param NoteList   $noteList   The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from NoteList.html.php.
     */
    private function part4(NoteList $noteList, RenderPass $renderPass): string
    {
        return '<h2>Notes '
            . $renderPass->child($noteList->count, 'NoteList.1')
            . "</h2>\n"
            . '<!--t:b-->'
            . ($noteList->links === []
                ? $this->part0()
                : $this->part3($noteList, $renderPass))
            . '<!--/t:b-->';
    }
}
