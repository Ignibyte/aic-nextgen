<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use InvalidArgumentException;

/** The selected theme's compiled region schema and literal page construction. */
interface PageLayouts
{
    /**
     * @param string          $title      The document title.
     * @param Regions         $regions    The page's filled regions.
     * @param LayoutName|null $layoutName The theme layout to use (#154); null for `html.phtml` and `page.phtml`.
     *
     * @throws InvalidArgumentException When a region cannot be constructed.
     *
     * @return Layout The document and page over those same regions.
     */
    public function create(string $title, Regions $regions, ?LayoutName $layoutName = null): Layout;
}
