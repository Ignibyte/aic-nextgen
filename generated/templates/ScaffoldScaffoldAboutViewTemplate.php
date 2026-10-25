<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Scaffold\Component\ScaffoldAboutView;

/**
 * ScaffoldAboutView's markup, compiled from
 * modules/scaffold/templates/components/scaffold-about-view.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ScaffoldScaffoldAboutViewTemplate
{
    use RendersMarkup;

    /**
     * @param ScaffoldAboutView $scaffoldAboutView The component.
     *
     * @return string Its markup.
     */
    public function render(ScaffoldAboutView $scaffoldAboutView): string
    {
        return $this->part0($scaffoldAboutView);
    }

    /**
     * @param ScaffoldAboutView $scaffoldAboutView The component.
     *
     * @return string Markup from modules/scaffold/templates/components/scaffold-about-view.tpl.php.
     */
    private function part0(ScaffoldAboutView $scaffoldAboutView): string
    {
        return '<p>'
            . self::text($scaffoldAboutView->title)
            . "</p>\n";
    }
}
