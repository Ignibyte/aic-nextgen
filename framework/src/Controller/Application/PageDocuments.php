<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\LayoutName;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\PageDocument;
use InvalidArgumentException;

/**
 * A Page controller's documents (#154): its regions in the theme's layout, titled with the app's name. A controller
 * may name a layout (`ThemeLayoutName::Focus`); each of its two files falls back on its own to `html.tpl.php` and
 * `page.tpl.php` when the theme ships no variant.
 */
final readonly class PageDocuments
{
    /**
     * @param AppConfig   $appConfig   The app's settings, for its name.
     * @param PageLayouts $pageLayouts The theme's compiled layouts.
     */
    public function __construct(
        private AppConfig $appConfig,
        private PageLayouts $pageLayouts,
    ) {
    }

    /**
     * @param Regions         $regions    The regions the page fills.
     * @param int             $status     200, 404 or 422.
     * @param LayoutName|null $layoutName The theme layout; null for the default.
     *
     * @throws InvalidArgumentException When the status is none of these, or a region is not the layout's.
     *
     * @return PageDocument The page.
     */
    public function page(Regions $regions, int $status, ?LayoutName $layoutName = null): PageDocument
    {
        return new PageDocument($this->pageLayouts->create($this->appConfig->name, $regions, $layoutName), $status);
    }

    /**
     * @param list<Component> $components The `content` region's components, in order.
     * @param int             $status     200, 404 or 422.
     * @param LayoutName|null $layoutName The theme layout; null for the default.
     *
     * @throws InvalidArgumentException When the status is none of these.
     *
     * @return PageDocument The page, with only its content region filled.
     */
    public function content(array $components, int $status, ?LayoutName $layoutName = null): PageDocument
    {
        return $this->page(new Regions([new Region('content', $components)]), $status, $layoutName);
    }
}
