<?php

declare(strict_types=1);

namespace App\Note\Form;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function explode;
use function is_string;
use function max;
use function preg_match;
use function strlen;

/**
 * What a request asks of the stock (#184): the stock list's and the CSV
 * export's (#209) search, prices, SKUs, uncounted flag and page, read from
 * its query fields, each malformed value asking for nothing.
 */
final readonly class StockQueries
{
    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `q`, `min_price`, `max_price`, `sku` (a
     *                                              comma-separated list), `uncounted` and `page`.
     *
     * @return StockQuery What it asks for; a malformed value asks for nothing.
     */
    public function query(ServerRequestInterface $serverRequest): StockQuery
    {
        $page = $this->field($serverRequest, 'page');

        return new StockQuery(
            $this->field($serverRequest, 'q'),
            self::price($this->field($serverRequest, 'min_price')),
            self::price($this->field($serverRequest, 'max_price')),
            self::skus($this->field($serverRequest, 'sku')),
            $this->field($serverRequest, 'uncounted') === '1',
            ctype_digit($page) && strlen($page) < 5 ? max(1, (int) $page) : 1,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query field.
     *
     * @return string Its value; empty when absent or not a string.
     */
    public function field(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
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
}
