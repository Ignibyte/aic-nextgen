<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Service\HtmlLayout;
use App\Theme\Components\ScaffoldAboutView\ScaffoldAboutView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /scaffold/about, made by bin/aic make:page (#122): ScaffoldAboutView in the site's layout.
 */
final readonly class ScaffoldAboutPage implements Page
{
    /**
     * @param HtmlLayout $htmlLayout Makes the page.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
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
        return $this->htmlLayout->content([new ScaffoldAboutView('Scaffold About')], 200);
    }
}
