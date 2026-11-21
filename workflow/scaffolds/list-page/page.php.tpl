<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use {namespace}\Form\{stem}ListQueries;
use {namespace}\Service\{stem}ListView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET {path}, made by bin/aic make:list-page (#324): the `{table}` rows,
 * filtered, sorted and a page at a time, as the query string asks.
 */
final readonly class {stem}ListPage implements Page
{
    /**
{pageDocParams}     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private {stem}ListQueries ${variable}ListQueries,
        private {stem}ListView ${variable}ListView,
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
        ${variable}ListQuery = $this->{variable}ListQueries->query($serverRequest);

        return $this->pageDocuments->content(
            $this->{variable}ListView->components(${variable}ListQuery),
            200,
            '{title}',
        );
    }
}
