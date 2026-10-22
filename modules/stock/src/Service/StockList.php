<?php

declare(strict_types=1);

namespace App\Stock\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use App\Stock\Component\StockLinks;
use App\Stock\Component\StockListing;
use App\Stock\Entity\StockItem;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

use function array_slice;
use function ctype_digit;
use function is_string;
use function max;
use function min;

/**
 * Builds the stock page's list (#74): the keyed component `content/stock-list`,
 * showing the last N items. It makes the component rather than being one, so
 * it is a service (#94). N comes from the `limit` query field; a request without
 * one (a refresh of the list alone) takes it from the props the marker
 * carried, so a refresh keeps it while a visit to `?limit=3` changes it (#83).
 */
final readonly class StockList
{
    /**
     * The list's path for Tessera.
     */
    private const string TARGET = 'content/stock-list';

    /**
     * @param EnvelopeCodec $envelopeCodec Seals the list's props.
     * @param TesseraProps  $tesseraProps  Reads them back, verified.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private TesseraProps $tesseraProps,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `limit`, maybe the list's props.
     * @param list<StockItem>        $items         Every item, oldest first.
     *
     * @throws InvalidEnvelope When the request's props for the list were altered.
     * @throws JsonException   When the props cannot be sealed.
     *
     * @return KeyedComponent The list, its limit sealed in its props.
     */
    public function component(ServerRequestInterface $serverRequest, array $items): KeyedComponent
    {
        $props = $this->tesseraProps->forTarget($serverRequest, self::TARGET);
        $query = self::queryLimit($serverRequest);
        $limit = self::limit($query === '' ? $props['limit'] ?? '' : $query);

        return new KeyedComponent(
            'stock-list',
            self::listing(array_slice($items, -$limit), $limit),
            $this->envelopeCodec->seal(['limit' => (string) $limit]),
        );
    }

    /**
     * @return StockLinks The links that show three or ten, and the one that refreshes the list alone.
     */
    public function links(): StockLinks
    {
        return new StockLinks(
            new Link('Show the last 3', '/stock?limit=3', false, self::TARGET),
            new Link('Show the last 10', '/stock?limit=10', false, self::TARGET),
            new Link('Refresh the list', '/stock', false, self::TARGET),
        );
    }

    /**
     * @param list<StockItem> $items The items to show, oldest first.
     * @param int             $limit How many the list shows at most.
     *
     * @return StockListing The list.
     */
    private static function listing(array $items, int $limit): StockListing
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = $item->line();
        }

        return new StockListing($limit, $lines);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string Its `limit` query field; empty when absent or not a string.
     */
    private static function queryLimit(ServerRequestInterface $serverRequest): string
    {
        $limit = $serverRequest->getQueryParams()['limit'] ?? '';

        return is_string($limit)
            ? $limit
            : '';
    }

    /**
     * @param string $limit A limit as sent.
     *
     * @return int It, 1 to 50; 10 when it is not a number.
     */
    private static function limit(string $limit): int
    {
        return ctype_digit($limit)
            ? max(1, min(50, (int) $limit))
            : 10;
    }
}
