<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Component\Application\DetailParts;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Routing\Domain\RouteNotFoundException;
use App\Scaffold\Service\ScaffoldNoteDetailView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET /scaffold/notes/{id:int}, made by bin/aic make:detail-page (#341): one `notes` row,
 * its fields in tabs, the one `tab` names open; 404 when there is no such row.
 */
final readonly class ScaffoldNoteDetailPage implements Page
{
    /**
     * @param PageDocuments          $pageDocuments          Makes the page.
     * @param DetailParts            $detailParts            Reads the open tab.
     * @param ScaffoldNoteDetailView $scaffoldNoteDetailView Makes its components.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private DetailParts $detailParts,
        private ScaffoldNoteDetailView $scaffoldNoteDetailView,
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
        $tab = $this->detailParts->tab($serverRequest, ScaffoldNoteDetailView::TABS);
        $id = $serverRequest->getAttribute('id');

        if (!is_string($id)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        $components = $this->scaffoldNoteDetailView->components($id, $tab);

        if ($components === null) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->pageDocuments->content($components, 200, 'Notes');
    }
}
