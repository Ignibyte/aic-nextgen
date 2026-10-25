<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Exception\UnknownComponent;
use AIC\Form\Contracts\FormToken;

/**
 * ThemeLayout's markup, compiled from themes/app/layouts/html.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ThemeAppHtmlTemplate
{
    use RendersMarkup;

    /**
     * @param ThemeLayout $themeLayout The component.
     * @param RenderPass  $renderPass  The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(ThemeLayout $themeLayout, RenderPass $renderPass): string
    {
        return $this->part1($themeLayout, $renderPass);
    }

    /**
     * @param ThemeLayout $themeLayout The component.
     * @param RenderPass  $renderPass  The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/html.tpl.php.
     */
    private function part0(ThemeLayout $themeLayout, RenderPass $renderPass): string
    {
        return self::text(FormToken::HEADER)
            . "\">\n"
            . $renderPass->inline($themeLayout->trackedAssets)
            . "</head>\n"
            . '<body><h1>'
            . self::text($themeLayout->name)
            . "</h1>\n"
            . $renderPass->inline($themeLayout->page)
            . "</body></html>\n";
    }

    /**
     * @param ThemeLayout $themeLayout The component.
     * @param RenderPass  $renderPass  The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/html.tpl.php.
     */
    private function part1(ThemeLayout $themeLayout, RenderPass $renderPass): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport"'
            . " content=\"width=device-width, initial-scale=1\">\n"
            . "<link rel=\"icon\" href=\"/assets/site/favicon.svg\" type=\"image/svg+xml\">\n"
            . '<title>'
            . self::text($themeLayout->name)
            . '</title><meta name="tessera-layout" content="'
            . self::text($themeLayout->identity)
            . "\">\n"
            . '<meta name="tessera-csrf-token" content="'
            . self::text($themeLayout->token)
            . '"><meta name="tessera-csrf-header" content="'
            . $this->part0($themeLayout, $renderPass);
    }
}
