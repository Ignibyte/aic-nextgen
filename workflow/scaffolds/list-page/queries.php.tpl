<?php

declare(strict_types=1);

namespace {namespace}\Form;

use AIC\Component\Application\ListQueries;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the query string of GET {path} into a `{stem}ListQuery`: its
 * filters, `sort` and `page`, each its default when it is not one the list
 * knows; made by bin/aic make:list-page (#324).
 */
final readonly class {stem}ListQueries
{
    /**
     * The columns the rows may be sorted by; the first is the default.
     */
    public const array SORTS = [
{sorts}    ];

    /**
     * @param ListQueries $listQueries Reads each value.
     */
    public function __construct(
        private ListQueries $listQueries,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return {stem}ListQuery What it asks for.
     */
    public function query(ServerRequestInterface $serverRequest): {stem}ListQuery
    {
        return new {stem}ListQuery(
            $this->listQueries->place($serverRequest, self::SORTS),
{reads}        );
    }
}
