<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Theme\Components\ScaffoldGauge\ScaffoldGauge;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /scaffold/gauge, made by bin/aic make:page (#122): ScaffoldGauge in the theme's layout.
 */
final readonly class ScaffoldGaugePage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws InvalidArgumentException When the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->content(
            [new ScaffoldGauge('Scaffold Gauge')],
            200,
            'Scaffold Gauge',
        );
    }
}
