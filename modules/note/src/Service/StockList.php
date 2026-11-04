<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function is_string;
use function max;
use function min;

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
     * The list, by its anchor: Tessera sends the list's props with it after the list's path, which sits inside
     * a panel and so is positional (#194); the page scopes its signal patch by it (#260).
     */
    public const string TARGET = '@' . self::ANCHOR;

    /**
     * The list's anchor (#188): its links target `@stock-list`, and Tessera finds its path on the page.
     */
    private const string ANCHOR = 'stock-list';

    /**
     * @param EnvelopeCodec   $envelopeCodec   Seals the list's props.
     * @param TesseraProps    $tesseraProps    Reads them back, verified.
     * @param StockRepository $stockRepository What the list's and the totals' sources read.
     * @param StockListLinks  $stockListLinks  The links that refresh the list by its anchor.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private TesseraProps $tesseraProps,
        private StockRepository $stockRepository,
        private StockListLinks $stockListLinks,
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
        $links = $this->stockListLinks->links(self::ANCHOR);

        return $stockQuery->isFiltered()
            ? [new SourcedComponent(new StockTotalsSource($this->stockRepository, $stockQuery)), $this->panel($keyedComponent), $links]
            : [$this->panel($keyedComponent), $links];
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
     * @param KeyedComponent $keyedComponent The list.
     *
     * @throws InvalidArgumentException When the panel's key breaks the key grammar.
     *
     * @return KeyedComponent The list in its panel, which keeps its own key in the region, so its path does not
     *                        move when the totals come and go.
     */
    private function panel(KeyedComponent $keyedComponent): KeyedComponent
    {
        return new KeyedComponent('stock-panel', new Panel('Recent additions', $keyedComponent));
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
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string Its `limit` query field; empty when absent or not a string.
     */
    private static function queryLimit(ServerRequestInterface $serverRequest): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists('limit', $query) && is_string($query['limit'])
            ? $query['limit']
            : '';
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
