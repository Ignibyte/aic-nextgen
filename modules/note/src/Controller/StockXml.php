<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\XmlResponse;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\StockQueries;
use App\Note\Service\StockFeed;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /stock.xml (#233): every stock item the query matches as an XML feed,
 * the list `/stock.csv` downloads, for a site that reads listings.
 */
final readonly class StockXml implements Api
{
    /**
     * @param StockQueries $stockQueries Reads the query.
     * @param StockFeed    $stockFeed    Builds the feed.
     */
    public function __construct(
        private StockQueries $stockQueries,
        private StockFeed $stockFeed,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: its search and price filters.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return ApiResponse The feed, as XML.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new XmlResponse($this->stockFeed->document($this->stockQueries->query($serverRequest)));
    }
}
