<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Scaffold\Component\ScaffoldCard;

/**
 * ScaffoldCard's markup, compiled from
 * modules/scaffold/templates/components/scaffold-card.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ScaffoldScaffoldCardTemplate
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
     * @return string Markup from modules/scaffold/templates/components/scaffold-card.tpl.php.
     */
    private function part0(ScaffoldCard $scaffoldCard): string
    {
        return '<p>'
            . self::text($scaffoldCard->title)
            . "</p>\n";
    }
}
