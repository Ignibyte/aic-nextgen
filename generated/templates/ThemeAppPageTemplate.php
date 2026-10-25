<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Exception\UnknownComponent;
use App\Theme\Layouts\Page;

/**
 * Page's markup, compiled from
 * themes/app/layouts/page.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ThemeAppPageTemplate
{
    /**
     * @param Page       $page       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(Page $page, RenderPass $renderPass): string
    {
        return $this->part1($page, $renderPass);
    }

    /**
     * @return string Markup from themes/app/layouts/page.tpl.php.
     */
    private function part0(): string
    {
        return "</footer>\n";
    }

    /**
     * @param Page       $page       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/page.tpl.php.
     */
    private function part1(Page $page, RenderPass $renderPass): string
    {
        return '<header>'
            . $renderPass->region($page->header)
            . "</header>\n"
            . '<main class="theme-page">'
            . $renderPass->region($page->content)
            . "</main>\n"
            . '<nav>'
            . $renderPass->region($page->nav)
            . "</nav>\n"
            . '<footer>'
            . $renderPass->region($page->footer)
            . $this->part0();
    }
}
