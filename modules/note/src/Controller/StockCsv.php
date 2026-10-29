<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\FileDownload;
use App\Note\Form\StockQueries;
use App\Note\Service\StockExport;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * `GET /stock.csv` (#209): the stock the list's query matches, written as CSV
 * and downloaded as `stock.csv`, one byte range honoured. An app's export job
 * writes its file the same way and a Stream sends it.
 */
final readonly class StockCsv implements Stream
{
    /**
     * @param StockQueries $stockQueries Reads the query.
     * @param StockExport  $stockExport  Writes the file.
     */
    public function __construct(
        private StockQueries $stockQueries,
        private StockExport $stockExport,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: the stock list's `q`, `min_price`, `max_price`,
     *                                              `sku` and `uncounted`.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     * @throws RuntimeException         When the stock cannot be read (a `QueryException`) or the file cannot be written.
     *
     * @return StreamResponse The file as a download, or one byte range of it.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $path = $this->stockExport->file($this->stockQueries->query($serverRequest));

        return new FileDownload($path, 'text/csv; charset=utf-8', 'stock.csv', $serverRequest);
    }
}
