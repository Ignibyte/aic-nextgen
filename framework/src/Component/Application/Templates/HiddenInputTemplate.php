<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\RendersMarkup;

/**
 * HiddenInput's markup, compiled from
 * framework/templates/components/hidden-input.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class HiddenInputTemplate
{
    use RendersMarkup;

    /**
     * @param HiddenInput $hiddenInput The component.
     *
     * @return string Its markup.
     */
    public function render(HiddenInput $hiddenInput): string
    {
        return $this->part0($hiddenInput);
    }

    /**
     * @param HiddenInput $hiddenInput The component.
     *
     * @return string Markup from framework/templates/components/hidden-input.tpl.php.
     */
    private function part0(HiddenInput $hiddenInput): string
    {
        return '<input type="hidden" name="'
            . self::text($hiddenInput->name)
            . '" value="'
            . self::text($hiddenInput->value)
            . '">';
    }
}
