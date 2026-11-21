<?php

declare(strict_types=1);

namespace App\Scaffold\Form;

use AIC\Component\Application\ListQueries;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the query string of GET /scaffold/books into a `ScaffoldBookListQuery`: its
 * filters, `sort` and `page`, each its default when it is not one the list
 * knows; made by bin/aic make:list-page (#324).
 */
final readonly class ScaffoldBookListQueries
{
    /**
     * The columns the rows may be sorted by; the first is the default.
     */
    public const array SORTS = [
        'title',
        'pages',
        'price',
    ];

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
     * @return ScaffoldBookListQuery What it asks for.
     */
    public function query(ServerRequestInterface $serverRequest): ScaffoldBookListQuery
    {
        return new ScaffoldBookListQuery(
            $this->listQueries->place($serverRequest, self::SORTS),
            $this->listQueries->text($serverRequest, 'title'),
            $this->listQueries->choice($serverRequest, 'retired', ['yes', 'no']),
        );
    }
}
