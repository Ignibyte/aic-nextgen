<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\PageDocument;
use InvalidArgumentException;

/**
 * Makes the app's pages through its selected compiled theme, titled with the app's name.
 */
final readonly class HtmlLayout
{
    /**
     * @param AppConfig   $appConfig   The app's settings.
     * @param PageLayouts $pageLayouts The explicitly selected compiled theme factory.
     */
    public function __construct(
        private AppConfig $appConfig,
        private PageLayouts $pageLayouts,
    ) {
    }

    /**
     * @param Regions $regions The regions the page fills.
     * @param int     $status  200, 404 or 422.
     *
     * @throws InvalidArgumentException When the status is none of these, or a region is not declared by the selected theme.
     *
     * @return PageDocument The page.
     */
    public function page(Regions $regions, int $status): PageDocument
    {
        return new PageDocument(
            $this->pageLayouts->create($this->appConfig->name, $regions),
            $status,
        );
    }

    /**
     * @param list<Component> $components The `content` region's components, in order.
     * @param int             $status     200, 404 or 422.
     *
     * @throws InvalidArgumentException When the status is none of these.
     *
     * @return PageDocument The page, with only its content region filled.
     */
    public function content(array $components, int $status): PageDocument
    {
        return $this->page(new Regions([new Region('content', $components)]), $status);
    }
}
