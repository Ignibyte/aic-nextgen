<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Exception\UnknownComponent;
use App\Theme\Layouts\ScaffoldSoloPage;

/**
 * ScaffoldSoloPage's markup, compiled from
 * themes/app/layouts/page--scaffold-solo.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ThemeAppPageScaffoldSoloTemplate
{
    /**
     * @param ScaffoldSoloPage $scaffoldSoloPage The component.
     * @param RenderPass       $renderPass       The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(ScaffoldSoloPage $scaffoldSoloPage, RenderPass $renderPass): string
    {
        return $this->part0($scaffoldSoloPage, $renderPass);
    }

    /**
     * @param ScaffoldSoloPage $scaffoldSoloPage The component.
     * @param RenderPass       $renderPass       The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/layouts/page--scaffold-solo.tpl.php.
     */
    private function part0(ScaffoldSoloPage $scaffoldSoloPage, RenderPass $renderPass): string
    {
        return '<main class="theme-page theme-scaffold-solo">'
            . $renderPass->region($scaffoldSoloPage->content)
            . "</main>\n";
    }
}
