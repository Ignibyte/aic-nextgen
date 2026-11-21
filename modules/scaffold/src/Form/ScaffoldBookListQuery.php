<?php

declare(strict_types=1);

namespace App\Scaffold\Form;

use AIC\Component\Domain\ListChoice;
use AIC\Component\Domain\ListPlace;

/**
 * What GET /scaffold/books is asked for, read from its query string by
 * `ScaffoldBookListQueries`; made by bin/aic make:list-page (#324).
 */
final readonly class ScaffoldBookListQuery
{
    /**
     * @param ListPlace  $listPlace Its sort and page.
     * @param string     $title     The text the title holds; empty for any.
     * @param ListChoice $retired   The retired.
     */
    public function __construct(
        public ListPlace $listPlace,
        public string $title,
        public ListChoice $retired,
    ) {
    }

    /**
     * @return array<string, string> The filters, by query-string name, which every link keeps.
     */
    public function scaffoldBookFilters(): array
    {
        return [
            'retired' => $this->retired->value,
            'title' => $this->title,
        ];
    }
}
