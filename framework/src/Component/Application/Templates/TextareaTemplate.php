<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\Textarea;

/**
 * Textarea's markup, compiled from
 * framework/templates/components/textarea.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class TextareaTemplate
{
    use RendersMarkup;

    /**
     * @param Textarea $textarea The component.
     *
     * @return string Its markup.
     */
    public function render(Textarea $textarea): string
    {
        return $this->part2($textarea);
    }

    /**
     * @param Textarea $textarea The component.
     *
     * @return string Markup from framework/templates/components/textarea.tpl.php.
     */
    private function part0(Textarea $textarea): string
    {
        return ' id="'
            . self::text($textarea->id)
            . '"';
    }

    /**
     * @param Textarea $textarea The component.
     *
     * @return string Markup from framework/templates/components/textarea.tpl.php.
     */
    private function part1(Textarea $textarea): string
    {
        return ' name="'
            . self::text($textarea->name)
            . '"';
    }

    /**
     * @param Textarea $textarea The component.
     *
     * @return string Markup from framework/templates/components/textarea.tpl.php.
     */
    private function part2(Textarea $textarea): string
    {
        return '<textarea'
            . ($textarea->id !== ''
                ? $this->part0($textarea)
                : '')
            . ($textarea->name !== ''
                ? $this->part1($textarea)
                : '')
            . ' class="aic-textarea">'
            . self::text($textarea->value)
            . '</textarea>';
    }
}
