<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Exception\UnknownComponent;
use App\Theme\Components\StockLinks\StockLinks;

/**
 * StockLinks's markup, compiled from
 * themes/app/components/StockLinks/stock-links.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class StockLinksTemplate
{
    /**
     * @param StockLinks $stockLinks The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(StockLinks $stockLinks, RenderPass $renderPass): string
    {
        return $this->part0($stockLinks, $renderPass);
    }

    /**
     * @param StockLinks $stockLinks The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/components/StockLinks/stock-links.tpl.php.
     */
    private function part0(StockLinks $stockLinks, RenderPass $renderPass): string
    {
        return '<p>'
            . $renderPass->child($stockLinks->three, 'StockLinks.1')
            . ' · '
            . $renderPass->child($stockLinks->ten, 'StockLinks.2')
            . ' · '
            . $renderPass->child($stockLinks->refresh, 'StockLinks.3')
            . '</p>';
    }
}
