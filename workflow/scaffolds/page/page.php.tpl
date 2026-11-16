<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
{layoutUse}use App\Theme\Components\{component}\{component};
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET {path}, made by bin/aic make:page (#122): {component} in the theme's {layoutName}layout.
 */
final readonly class {class} implements Page
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
            [new {component}('{title}')],
            200,
            '{title}',{layoutArgument}
        );
    }
}
