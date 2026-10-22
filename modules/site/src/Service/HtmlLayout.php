<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\TrackedAssets;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\PageDocument;
use App\Site\Component\AppLayout;
use InvalidArgumentException;

/**
 * Makes the app's pages on AppLayout, titled with the app's name.
 */
final readonly class HtmlLayout
{
    /**
     * @param AppConfig $appConfig The app's settings.
     */
    public function __construct(
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param Regions $regions The regions the page fills.
     * @param int     $status  200, 404 or 422.
     *
     * @throws InvalidArgumentException When the status is none of these, or a region is one AppLayout does not place.
     *
     * @return PageDocument The page.
     */
    public function page(Regions $regions, int $status): PageDocument
    {
        $trackedAssets = new TrackedAssets('/assets/aic.css', $this->appConfig->tessera->scriptPath());

        return new PageDocument(
            new AppLayout($this->appConfig->name, $trackedAssets, $regions, $regions->region('content'), $regions->region('nav')),
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
