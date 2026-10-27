<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Generated\ThemeLayoutName;
use App\Note\Entity\StockDay;
use App\Note\Repository\StockReportRepository;
use App\Note\Repository\StockRepository;
use App\Note\Service\StockReportDay;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * `GET /stock/days` (#191): the stock report, the latest days stock came in
 * (or one `day`) read from the `stock_days` view, and what those items are
 * worth by whether they are certified.
 */
final readonly class StockDaysPage implements Page
{
    /**
     * @param PageDocuments         $pageDocuments         Makes the page.
     * @param StockRepository       $stockRepository       The stock, for its worth by certification.
     * @param StockReportRepository $stockReportRepository The report's days.
     * @param StockReportDay        $stockReportDay        Reads the day asked for.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private StockRepository $stockRepository,
        private StockReportRepository $stockReportRepository,
        private StockReportDay $stockReportDay,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with an optional `day` (`YYYY-MM-DD`).
     *
     * @throws QueryException When the report cannot be read.
     * @throws LogicException When a constructed value is invalid.
     *
     * @return PageResponse The report.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $day = $this->stockReportDay->day($serverRequest);
        $worth = $this->stockRepository->worthByCertification($day);
        $days = array_map(static fn (StockDay $stockDay): string => $stockDay->line(), $this->stockReportRepository->days(10, $day));

        return $this->pageDocuments->content([
            new Heading('Stock by day'),
            new LineList('stock-days', $days, 'Nothing in stock yet.'),
            new Paragraph('Certified: $' . ($worth['true'] ?? '0') . '; not certified: $' . ($worth['false'] ?? '0') . '.', 'stock-worth'),
        ], 200, 'Stock by day', ThemeLayoutName::Focus);
    }
}
