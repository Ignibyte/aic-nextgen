<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Exception\UnknownComponent;
use App\Theme\Layouts\FocusPage;

/**
 * FocusPage's markup, compiled from
 * themes/app/layouts/page--focus.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ThemeAppPageFocusTemplate
{
    /**
     * @param FocusPage  $focusPage  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(FocusPage $focusPage, RenderPass $renderPass): string
    {
        return $this->part0($focusPage, $renderPass);
    }

    /**
     * @param FocusPage  $focusPage  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/page--focus.tpl.php.
     */
    private function part0(FocusPage $focusPage, RenderPass $renderPass): string
    {
        return '<main class="theme-page theme-focus">'
            . $renderPass->region($focusPage->content)
            . "</main>\n"
            . '<nav>'
            . $renderPass->region($focusPage->nav)
            . "</nav>\n";
    }
}
