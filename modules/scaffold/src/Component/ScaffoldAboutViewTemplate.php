<?php

declare(strict_types=1);

namespace App\Scaffold\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * ScaffoldAboutView's markup, compiled from ScaffoldAboutView.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ScaffoldAboutViewTemplate
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
     * @return string Markup from ScaffoldAboutView.html.php.
     */
    private function part0(ScaffoldAboutView $scaffoldAboutView): string
    {
        return '<p>'
            . self::text($scaffoldAboutView->title)
            . "</p>\n";
    }
}
