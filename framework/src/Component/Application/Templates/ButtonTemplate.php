<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\RendersMarkup;

/**
 * Button's markup, compiled from
 * framework/templates/components/button.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ButtonTemplate
{
    use RendersMarkup;

    /**
     * @param Button $button The component.
     *
     * @return string Its markup.
     */
    public function render(Button $button): string
    {
        return $this->part2($button);
    }

    /**
     * @param Button $button The component.
     *
     * @return string Markup from framework/templates/components/button.tpl.php.
     */
    private function part0(Button $button): string
    {
        return ' id="'
            . self::text($button->id)
            . '"';
    }

    /**
     * @param Button $button The component.
     *
     * @return string Markup from framework/templates/components/button.tpl.php.
     */
    private function part1(Button $button): string
    {
        return ' type="'
            . self::text($button->type)
            . '"';
    }

    /**
     * @param Button $button The component.
     *
     * @return string Markup from framework/templates/components/button.tpl.php.
     */
    private function part2(Button $button): string
    {
        return '<button'
            . ($button->id !== ''
                ? $this->part0($button)
                : '')
            . ($button->type !== ''
                ? $this->part1($button)
                : '')
            . ' class="aic-button">'
            . self::text($button->label)
            . '</button>';
    }
}
