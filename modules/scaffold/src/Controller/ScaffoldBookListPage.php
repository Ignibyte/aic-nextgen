<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Form\ScaffoldBookListQueries;
use App\Scaffold\Service\ScaffoldBookListView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /scaffold/books, made by bin/aic make:list-page (#324): the `scaffold_books` rows,
 * filtered, sorted and a page at a time, as the query string asks.
 */
final readonly class ScaffoldBookListPage implements Page
{
    /**
     * @param PageDocuments           $pageDocuments           Makes the page.
     * @param ScaffoldBookListQueries $scaffoldBookListQueries Reads the query string.
     * @param ScaffoldBookListView    $scaffoldBookListView    Makes its components.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private ScaffoldBookListQueries $scaffoldBookListQueries,
        private ScaffoldBookListView $scaffoldBookListView,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its filters, `sort` and `page`.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort, a filter or the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $scaffoldBookListQuery = $this->scaffoldBookListQueries->query($serverRequest);

        return $this->pageDocuments->content(
            $this->scaffoldBookListView->components($scaffoldBookListQuery),
            200,
            'Scaffold books',
        );
    }
}
