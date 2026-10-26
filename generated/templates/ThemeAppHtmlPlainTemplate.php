<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Exception\UnknownComponent;
use AIC\Form\Contracts\FormToken;

/**
 * ThemeLayout's markup, compiled from
 * themes/app/layouts/html--plain.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ThemeAppHtmlPlainTemplate
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
        return $this->part2($themeLayout, $renderPass);
    }

    /**
     * @return string Markup from themes/app/layouts/html--plain.tpl.php.
     */
    private function part0(): string
    {
        return "</body>\n"
            . "</html>\n";
    }

    /**
     * @param ThemeLayout $themeLayout The component.
     * @param RenderPass  $renderPass  The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/html--plain.tpl.php.
     */
    private function part1(ThemeLayout $themeLayout, RenderPass $renderPass): string
    {
        return '<meta name="tessera-csrf-token" content="'
            . self::text($themeLayout->token)
            . "\">\n"
            . '<title>'
            . self::text($themeLayout->name)
            . "</title>\n"
            . "<link rel=\"icon\" href=\"/assets/themes/app/favicon.svg\" type=\"image/svg+xml\">\n"
            . $renderPass->inline($themeLayout->trackedAssets)
            . "</head>\n"
            . "<body class=\"plain\">\n"
            . $renderPass->inline($themeLayout->page)
            . $this->part0();
    }

    /**
     * @param ThemeLayout $themeLayout The component.
     * @param RenderPass  $renderPass  The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/html--plain.tpl.php.
     */
    private function part2(ThemeLayout $themeLayout, RenderPass $renderPass): string
    {
        return "<!doctype html>\n"
            . "<html lang=\"en\" class=\"plain\">\n"
            . "<head>\n"
            . "<meta charset=\"utf-8\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . '<meta name="tessera-layout" content="'
            . self::text($themeLayout->identity)
            . "\">\n"
            . '<meta name="tessera-csrf-header" content="'
            . self::text(FormToken::HEADER)
            . "\">\n"
            . $this->part1($themeLayout, $renderPass);
    }
}
