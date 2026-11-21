<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\ListChoice;
use AIC\Component\Domain\ListPlace;
use AIC\Component\Domain\ListSort;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function in_array;
use function is_string;
use function max;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Reads a list's state from its query string (#324): a search text, a
 * choice, and where it stands: the sort and the page. A value it does not know reads as none, so
 * a link someone altered shows the list as it is by default.
 */
final readonly class ListQueries
{
    /**
     * The longest search text it keeps, in bytes.
     */
    private const int LONGEST = 100;

    /**
     * The most digits a page number has.
     */
    private const int PAGE_DIGITS = 5;

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          The query-string name.
     *
     * @return string The text, trimmed; empty when there is none or it is longer than 100 bytes.
     */
    public function text(ServerRequestInterface $serverRequest, string $name): string
    {
        $text = trim($this->value($serverRequest, $name));

        return strlen($text) <= self::LONGEST
            ? $text
            : '';
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          The query-string name.
     * @param non-empty-list<string> $values        Every value it may take.
     *
     * @return ListChoice The value chosen, or none when the query string names none of them.
     */
    public function choice(ServerRequestInterface $serverRequest, string $name, array $values): ListChoice
    {
        $value = $this->value($serverRequest, $name);

        return new ListChoice(in_array($value, $values, true) ? $value : '', $values);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with `sort` and `page`.
     * @param non-empty-list<string> $columns       The columns it may sort by; the first is the default, ascending.
     *
     * @return ListPlace The sort and the page asked for, each its default when the query string names no other.
     */
    public function place(ServerRequestInterface $serverRequest, array $columns): ListPlace
    {
        return new ListPlace($this->sort($serverRequest, $columns), $this->page($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with `sort=<column>` or `sort=-<column>`.
     * @param non-empty-list<string> $columns       The columns it may sort by; the first is the default, ascending.
     *
     * @return ListSort The sort asked for, or the default when it names another column.
     */
    private function sort(ServerRequestInterface $serverRequest, array $columns): ListSort
    {
        $sort = $this->value($serverRequest, 'sort');
        $descending = str_starts_with($sort, '-');
        $column = $descending
            ? substr($sort, 1)
            : $sort;

        return in_array($column, $columns, true)
            ? new ListSort($column, $descending)
            : new ListSort($columns[0]);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with `page=<n>`.
     *
     * @return int The page asked for, from 1; 1 when it is not a number of up to five digits.
     */
    private function page(ServerRequestInterface $serverRequest): int
    {
        $page = $this->value($serverRequest, 'page');

        return ctype_digit($page) && strlen($page) <= self::PAGE_DIGITS
            ? max(1, (int) $page)
            : 1;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          The query-string name.
     *
     * @return string Its value; empty when it is missing or not text.
     */
    private function value(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
    }
}
