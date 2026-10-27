<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use App\Theme\Components\StockLinks\StockLinks;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function explode;
use function is_string;
use function max;
use function min;
use function preg_match;
use function strlen;

/**
 * Builds the stock page's list (#74): the keyed component `content/stock-list`,
 * showing the last N items, read from its source when it is rendered (#192). It makes the component rather than being one, so
 * it is a service (#94). N comes from the `limit` query field; a request without
 * one (a refresh of the list alone) takes it from the props the marker
 * carried, so a refresh keeps it while a visit to `?limit=3` changes it (#83).
 */
final readonly class StockList
{
    /**
     * The most items the list shows, so the most a page reads (#159).
     */
    public const int MOST = 50;

    /**
     * The list's path, which Tessera sends its props under.
     */
    private const string TARGET = 'content/stock-list';

    /**
     * The list's anchor (#188): its links target `@stock-list`, and Tessera finds its path on the page.
     */
    private const string ANCHOR = 'stock-list';

    /**
     * @param EnvelopeCodec   $envelopeCodec   Seals the list's props.
     * @param TesseraProps    $tesseraProps    Reads them back, verified.
     * @param StockRepository $stockRepository What the list's and the totals' sources read.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private TesseraProps $tesseraProps,
        private StockRepository $stockRepository,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `limit`, maybe the list's props.
     * @param StockQuery             $stockQuery    What the list asks for.
     * @param string                 $version       What its items are now (`version()`), sealed in its props (#193).
     *
     * @throws InvalidEnvelope          When the request's props for the list were altered.
     * @throws JsonException            When the props cannot be sealed.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return list<Component> The page's stock parts: the totals of a filtered list, the list, and its links.
     */
    public function parts(ServerRequestInterface $serverRequest, StockQuery $stockQuery, string $version): array
    {
        $keyedComponent = $this->component($serverRequest, $stockQuery, $version);

        return $stockQuery->isFiltered()
            ? [new SourcedComponent(new StockTotalsSource($this->stockRepository, $stockQuery)), $keyedComponent, $this->links()]
            : [$keyedComponent, $this->links()];
    }

    /**
     * @param StockQuery $stockQuery What the list asks for.
     *
     * @throws QueryException           When the items cannot be counted.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return string What the items the list matches are now (#193).
     */
    public function version(StockQuery $stockQuery): string
    {
        return $this->stockRepository->version($stockQuery);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, maybe a poll of the list.
     * @param string                 $version       What the list's items are now.
     *
     * @throws InvalidEnvelope When the request's props for the list were altered.
     *
     * @return bool Whether it is a poll of the list alone, sent with that version: nothing to send (#193).
     */
    public function unchanged(ServerRequestInterface $serverRequest, string $version): bool
    {
        return $this->tesseraProps->unchanged($serverRequest, self::TARGET, $version);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `q`, `min_price`, `max_price`, `sku` (a
     *                                              comma-separated list), `uncounted` and `page`.
     *
     * @return StockQuery What the list asks for; a malformed value asks for nothing (#184).
     */
    public function query(ServerRequestInterface $serverRequest): StockQuery
    {
        $page = self::field($serverRequest, 'page');

        return new StockQuery(
            self::field($serverRequest, 'q'),
            self::price(self::field($serverRequest, 'min_price')),
            self::price(self::field($serverRequest, 'max_price')),
            self::skus(self::field($serverRequest, 'sku')),
            self::field($serverRequest, 'uncounted') === '1',
            ctype_digit($page) && strlen($page) < 5 ? max(1, (int) $page) : 1,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `limit`, maybe the list's props.
     * @param StockQuery             $stockQuery    What the list asks for.
     * @param string                 $version       What its items are now (`version()`), sealed in its props (#193).
     *
     * @throws InvalidEnvelope          When the request's props for the list were altered.
     * @throws JsonException            When the props cannot be sealed.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return KeyedComponent The list, its limit and version sealed in its props, its items read only when it is
     *                        rendered (#192); it polls its own URL.
     */
    private function component(
        ServerRequestInterface $serverRequest,
        StockQuery $stockQuery,
        string $version,
    ): KeyedComponent {
        $props = $this->tesseraProps->forTarget($serverRequest, self::TARGET);
        $query = self::queryLimit($serverRequest);
        $limit = self::limit($query === '' ? $props['limit'] ?? '' : $query);

        return new KeyedComponent(
            'stock-list',
            new SourcedComponent(new StockListSource($this->stockRepository, $stockQuery, $limit, self::poll($serverRequest))),
            $this->envelopeCodec->seal(['limit' => (string) $limit, 'version' => $version]),
            self::ANCHOR,
        );
    }

    /**
     * @return StockLinks The links that show three or ten, and the one that refreshes the list alone.
     */
    private function links(): StockLinks
    {
        return new StockLinks(
            new Link('Show the last 3', '/stock?limit=3', false, '@' . self::ANCHOR),
            new Link('Show the last 10', '/stock?limit=10', false, '@' . self::ANCHOR),
            new Link('Refresh the list', '/stock', false, '@' . self::ANCHOR),
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string Its `limit` query field; empty when absent or not a string.
     */
    private static function queryLimit(ServerRequestInterface $serverRequest): string
    {
        return self::field($serverRequest, 'limit');
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string Its path and query: what the list polls, so a poll asks for the list the page shows.
     */
    private static function poll(ServerRequestInterface $serverRequest): string
    {
        $uri = $serverRequest->getUri();
        $query = $uri->getQuery();

        return $query === ''
            ? $uri->getPath()
            : $uri->getPath() . '?' . $query;
    }

    /**
     * @param string $price A price as sent.
     *
     * @return string It when it is whole dollars or dollars and cents; else empty.
     */
    private static function price(string $price): string
    {
        return preg_match('/^\\d{1,10}(\\.\\d{1,2})?$/', $price) === 1
            ? $price
            : '';
    }

    /**
     * @param string $skus SKUs as sent, separated by commas.
     *
     * @return list<string> Each well-formed SKU, in order; a malformed one is left out.
     */
    private static function skus(string $skus): array
    {
        $kept = [];

        foreach (explode(',', $skus) as $sku) {
            if (preg_match('/^[A-Z0-9-]{1,40}$/', $sku) !== 1) {
                continue;
            }

            $kept[] = $sku;
        }

        return $kept;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query field.
     *
     * @return string Its value; empty when absent or not a string.
     */
    private static function field(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
    }

    /**
     * @param string $limit A limit as sent.
     *
     * @return int It, 1 to `MOST`; 10 when it is not a number.
     */
    private static function limit(string $limit): int
    {
        return ctype_digit($limit)
            ? max(1, min(self::MOST, (int) $limit))
            : 10;
    }
}
