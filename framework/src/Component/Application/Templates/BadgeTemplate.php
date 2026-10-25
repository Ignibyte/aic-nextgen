<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Badge;
use AIC\Component\Domain\RendersMarkup;

/**
 * Badge's markup, compiled from
 * framework/templates/components/badge.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class BadgeTemplate
{
    use RendersMarkup;

    /**
     * @param Badge $badge The component.
     *
     * @return string Its markup.
     */
    public function render(Badge $badge): string
    {
        return $this->part1($badge);
    }

    /**
     * @param Badge $badge The component.
     *
     * @return string Markup from framework/templates/components/badge.tpl.php.
     */
    private function part0(Badge $badge): string
    {
        return ' id="'
            . self::text($badge->id)
            . '"';
    }

    /**
     * @param Badge $badge The component.
     *
     * @return string Markup from framework/templates/components/badge.tpl.php.
     */
    private function part1(Badge $badge): string
    {
        return '<span'
            . ($badge->id !== ''
                ? $this->part0($badge)
                : '')
            . ' class="aic-badge">'
            . self::text($badge->text)
            . '</span>';
    }
}
