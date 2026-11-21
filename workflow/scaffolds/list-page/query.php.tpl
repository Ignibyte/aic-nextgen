<?php

declare(strict_types=1);

namespace {namespace}\Form;

{queryUses}use AIC\Component\Domain\ListPlace;

/**
 * What GET {path} is asked for, read from its query string by
 * `{stem}ListQueries`; made by bin/aic make:list-page (#324).
 */
final readonly class {stem}ListQuery
{
    /**
{queryParams}     */
    public function __construct(
        public ListPlace $listPlace,
{queryProps}    ) {
    }

    /**
     * @return array<string, string> The filters, by query-string name, which every link keeps.
     */
    public function {variable}Filters(): array
    {
        return [
{queryFilters}        ];
    }
}
