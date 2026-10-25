<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Input;
use AIC\Component\Domain\RendersMarkup;

/**
 * Input's markup, compiled from
 * framework/templates/components/input.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class InputTemplate
{
    use RendersMarkup;

    /**
     * @param Input $input The component.
     *
     * @return string Its markup.
     */
    public function render(Input $input): string
    {
        return $this->part4($input);
    }

    /**
     * @param Input $input The component.
     *
     * @return string Markup from framework/templates/components/input.tpl.php.
     */
    private function part0(Input $input): string
    {
        return ' id="'
            . self::text($input->id)
            . '"';
    }

    /**
     * @param Input $input The component.
     *
     * @return string Markup from framework/templates/components/input.tpl.php.
     */
    private function part1(Input $input): string
    {
        return ' name="'
            . self::text($input->name)
            . '"';
    }

    /**
     * @return string Markup from framework/templates/components/input.tpl.php.
     */
    private function part2(): string
    {
        return ' aria-invalid="true"';
    }

    /**
     * @param Input $input The component.
     *
     * @return string Markup from framework/templates/components/input.tpl.php.
     */
    private function part3(Input $input): string
    {
        return ($input->invalid
            ? $this->part2()
            : '')
            . ' class="aic-input">';
    }

    /**
     * @param Input $input The component.
     *
     * @return string Markup from framework/templates/components/input.tpl.php.
     */
    private function part4(Input $input): string
    {
        return '<input'
            . ($input->id !== ''
                ? $this->part0($input)
                : '')
            . ($input->name !== ''
                ? $this->part1($input)
                : '')
            . ' value="'
            . self::text($input->value)
            . '"'
            . $this->part3($input);
    }
}
