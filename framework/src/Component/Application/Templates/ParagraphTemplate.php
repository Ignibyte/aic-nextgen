<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\RendersMarkup;

/**
 * Paragraph's markup, compiled from
 * framework/templates/components/paragraph.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ParagraphTemplate
{
    use RendersMarkup;

    /**
     * @param Paragraph $paragraph The component.
     *
     * @return string Its markup.
     */
    public function render(Paragraph $paragraph): string
    {
        return $this->part1($paragraph);
    }

    /**
     * @param Paragraph $paragraph The component.
     *
     * @return string Markup from framework/templates/components/paragraph.tpl.php.
     */
    private function part0(Paragraph $paragraph): string
    {
        return ' id="'
            . self::text($paragraph->id)
            . '"';
    }

    /**
     * @param Paragraph $paragraph The component.
     *
     * @return string Markup from framework/templates/components/paragraph.tpl.php.
     */
    private function part1(Paragraph $paragraph): string
    {
        return '<p'
            . ($paragraph->id !== ''
                ? $this->part0($paragraph)
                : '')
            . '>'
            . self::text($paragraph->text)
            . '</p>';
    }
}
