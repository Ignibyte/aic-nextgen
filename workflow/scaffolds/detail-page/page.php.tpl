<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Component\Application\DetailParts;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Routing\Domain\RouteNotFoundException;
use {namespace}\Service\{stem}DetailView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET {route}, made by bin/aic make:detail-page (#341): one `{table}` row,
 * its fields in tabs, the one `tab` names open; 404 when there is no such row.
 */
final readonly class {stem}DetailPage implements Page
{
    /**
{pageDocParams}     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private DetailParts $detailParts,
        private {stem}DetailView ${variable}DetailView,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the row's `id`, and its `tab`.
     *
     * @throws RouteNotFoundException   When there is no such row, which AIC answers 404.
     * @throws QueryException           When the row cannot be read.
     * @throws InvalidArgumentException When a name, the anchor or the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $tab = $this->detailParts->tab($serverRequest, {stem}DetailView::TABS);
        $id = $serverRequest->getAttribute('id');

        if (!is_string($id)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        $components = $this->{variable}DetailView->components($id, $tab);

        if ($components === null) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->pageDocuments->content($components, 200, '{title}');
    }
}
