<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\RendersMarkup;

/**
 * Checkbox's markup, compiled from
 * framework/templates/components/checkbox.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class CheckboxTemplate
{
    use RendersMarkup;

    /**
     * @param Checkbox $checkbox The component.
     *
     * @return string Its markup.
     */
    public function render(Checkbox $checkbox): string
    {
        return $this->part4($checkbox);
    }

    /**
     * @param Checkbox $checkbox The component.
     *
     * @return string Markup from framework/templates/components/checkbox.tpl.php.
     */
    private function part0(Checkbox $checkbox): string
    {
        return ' id="'
            . self::text($checkbox->id)
            . '"';
    }

    /**
     * @param Checkbox $checkbox The component.
     *
     * @return string Markup from framework/templates/components/checkbox.tpl.php.
     */
    private function part1(Checkbox $checkbox): string
    {
        return ' name="'
            . self::text($checkbox->name)
            . '"';
    }

    /**
     * @return string Markup from framework/templates/components/checkbox.tpl.php.
     */
    private function part2(): string
    {
        return ' checked';
    }

    /**
     * @return string Markup from framework/templates/components/checkbox.tpl.php.
     */
    private function part3(): string
    {
        return ' class="aic-checkbox">';
    }

    /**
     * @param Checkbox $checkbox The component.
     *
     * @return string Markup from framework/templates/components/checkbox.tpl.php.
     */
    private function part4(Checkbox $checkbox): string
    {
        return '<input type="checkbox"'
            . ($checkbox->id !== ''
                ? $this->part0($checkbox)
                : '')
            . ($checkbox->name !== ''
                ? $this->part1($checkbox)
                : '')
            . ' value="1"'
            . ($checkbox->checked
                ? $this->part2()
                : '')
            . $this->part3();
    }
}
