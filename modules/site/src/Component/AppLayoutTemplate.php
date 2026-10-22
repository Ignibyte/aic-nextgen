<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use AIC\Form\Contracts\FormToken;

/**
 * AppLayout's markup, compiled from AppLayout.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class AppLayoutTemplate
{
    use RendersMarkup;

    /**
     * @param AppLayout  $appLayout  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Its markup.
     */
    public function render(AppLayout $appLayout, RenderPass $renderPass): string
    {
        return $this->part2($appLayout, $renderPass);
    }

    /**
     * @return string Markup from AppLayout.html.php.
     */
    private function part0(): string
    {
        return "</body></html>\n";
    }

    /**
     * @param AppLayout  $appLayout  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from AppLayout.html.php.
     */
    private function part1(AppLayout $appLayout, RenderPass $renderPass): string
    {
        return self::text(FormToken::HEADER)
            . "\">\n"
            . $renderPass->inline($appLayout->trackedAssets)
            . "</head>\n"
            . '<body><h1>'
            . self::text($appLayout->name)
            . "</h1>\n"
            . $renderPass->region($appLayout->content)
            . "\n"
            . $renderPass->region($appLayout->nav)
            . "\n"
            . $this->part0();
    }

    /**
     * @param AppLayout  $appLayout  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from AppLayout.html.php.
     */
    private function part2(AppLayout $appLayout, RenderPass $renderPass): string
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
            . $this->part1($appLayout, $renderPass);
    }
}
