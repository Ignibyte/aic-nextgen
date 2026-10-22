<?php

declare(strict_types=1);

namespace App\Scaffold\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * ScaffoldCard's markup, compiled from ScaffoldCard.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ScaffoldCardTemplate
{
    use RendersMarkup;

    /**
     * @param ScaffoldCard $scaffoldCard The component.
     *
     * @return string Its markup.
     */
    public function render(ScaffoldCard $scaffoldCard): string
    {
        return $this->part0($scaffoldCard);
    }

    /**
     * @param ScaffoldCard $scaffoldCard The component.
     *
     * @return string Markup from ScaffoldCard.html.php.
     */
    private function part0(ScaffoldCard $scaffoldCard): string
    {
        return '<p>'
            . self::text($scaffoldCard->title)
            . "</p>\n";
    }
}
