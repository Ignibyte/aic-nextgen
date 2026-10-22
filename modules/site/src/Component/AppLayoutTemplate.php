<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;
use AIC\Form\Contracts\FormToken;

/**
 * AppLayout's markup, compiled from AppLayout.html.php by bin/aic-build (#97):
 * edit the template and run composer run build, never this file.
 */
final readonly class AppLayoutTemplate
{
    use RendersMarkup;

    /**
     * @param AppLayout $appLayout The component.
     *
     * @return Html Its markup.
     */
    public function render(AppLayout $appLayout): Html
    {
        return new Html($this->part2($appLayout));
    }

    /**
     * @return string Markup from AppLayout.html.php.
     */
    private function part0(): string
    {
        return "</body></html>\n";
    }

    /**
     * @param AppLayout $appLayout The component.
     *
     * @return string Markup from AppLayout.html.php.
     */
    private function part1(AppLayout $appLayout): string
    {
        return self::text(FormToken::HEADER)
            . "\">\n"
            . $appLayout->trackedAssets->markup()
            . "</head>\n"
            . '<body><h1>'
            . self::text($appLayout->name)
            . "</h1>\n"
            . $appLayout->content->render()->markup()
            . "\n"
            . $appLayout->nav->render()->markup()
            . "\n"
            . $this->part0();
    }

    /**
     * @param AppLayout $appLayout The component.
     *
     * @return string Markup from AppLayout.html.php.
     */
    private function part2(AppLayout $appLayout): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport"'
            . " content=\"width=device-width, initial-scale=1\">\n"
            . "<link rel=\"icon\" href=\"/assets/site/favicon.svg\" type=\"image/svg+xml\">\n"
            . '<title>'
            . self::text($appLayout->name)
            . '</title><meta name="tessera-layout" content="'
            . self::text(AppLayout::ID)
            . "\">\n"
            . '<meta name="tessera-csrf-token" content="'
            . self::text($appLayout->csrfToken)
            . '"><meta name="tessera-csrf-header" content="'
            . $this->part1($appLayout);
    }
}
