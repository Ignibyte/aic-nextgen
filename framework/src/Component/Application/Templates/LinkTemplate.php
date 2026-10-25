<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Link;
use AIC\Component\Domain\RendersMarkup;

/**
 * Link's markup, compiled from
 * framework/templates/components/link.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class LinkTemplate
{
    use RendersMarkup;

    /**
     * @param Link $link The component.
     *
     * @return string Its markup.
     */
    public function render(Link $link): string
    {
        return $this->part4($link);
    }

    /**
     * @param Link $link The component.
     *
     * @return string Markup from framework/templates/components/link.tpl.php.
     */
    private function part0(Link $link): string
    {
        return ' href="'
            . self::text($link->href)
            . '"';
    }

    /**
     * @param Link $link The component.
     *
     * @return string Markup from framework/templates/components/link.tpl.php.
     */
    private function part1(Link $link): string
    {
        return ' data-target="'
            . self::text($link->target)
            . '"';
    }

    /**
     * @return string Markup from framework/templates/components/link.tpl.php.
     */
    private function part2(): string
    {
        return ' aria-current="page"';
    }

    /**
     * @param Link $link The component.
     *
     * @return string Markup from framework/templates/components/link.tpl.php.
     */
    private function part3(Link $link): string
    {
        return self::text($link->label)
            . '</a>';
    }

    /**
     * @param Link $link The component.
     *
     * @return string Markup from framework/templates/components/link.tpl.php.
     */
    private function part4(Link $link): string
    {
        return '<a'
            . ($link->href !== ''
                ? $this->part0($link)
                : '')
            . ($link->target !== ''
                ? $this->part1($link)
                : '')
            . ($link->current
                ? $this->part2()
                : '')
            . ' class="aic-link">'
            . $this->part3($link);
    }
}
