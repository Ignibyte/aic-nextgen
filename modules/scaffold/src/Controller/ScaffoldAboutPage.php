<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Generated\ThemeLayoutName;
use App\Theme\Components\ScaffoldAboutView\ScaffoldAboutView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /scaffold/about, made by bin/aic make:page (#122): ScaffoldAboutView in the theme's ScaffoldSolo layout.
 */
final readonly class ScaffoldAboutPage implements Page
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
        return $this->pageDocuments->content([new ScaffoldAboutView('Scaffold About')], 200, ThemeLayoutName::ScaffoldSolo);
    }
}
