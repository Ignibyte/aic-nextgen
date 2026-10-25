<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\RendersMarkup;

/**
 * Heading's markup, compiled from
 * framework/templates/components/heading.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class HeadingTemplate
{
    use RendersMarkup;

    /**
     * @param Heading $heading The component.
     *
     * @return string Its markup.
     */
    public function render(Heading $heading): string
    {
        return $this->part0($heading);
    }

    /**
     * @param Heading $heading The component.
     *
     * @return string Markup from framework/templates/components/heading.tpl.php.
     */
    private function part0(Heading $heading): string
    {
        return '<h2>'
            . self::text($heading->text)
            . '</h2>';
    }
}
