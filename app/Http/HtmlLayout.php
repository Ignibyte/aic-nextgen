<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\PageDocument;
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
        return new PageDocument(new AppLayout($this->appConfig->name, $regions), $status);
    }

    /**
     * @param string $markup The `content` region's markup, already escaped.
     * @param int    $status 200, 404 or 422.
     *
     * @throws InvalidArgumentException When the status is none of these.
     *
     * @return PageDocument The page, with only its content region filled.
     */
    public function content(string $markup, int $status): PageDocument
    {
        return $this->page(new Regions([new Region('content', [new Html($markup)])]), $status);
    }
}
