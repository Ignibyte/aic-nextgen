<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A list's filters (#324): a GET form of search boxes and selects, with the
 * query values it keeps as hidden inputs, such as the sort. It renders with
 * the class `aic-filter-bar`; `FilterForm` stays the one-field search.
 */
final readonly class FilterBar implements Component
{
    /**
     * @param string            $action The same-origin path the form sends to.
     * @param list<FilterField> $fields The fields, in order.
     * @param list<HiddenInput> $kept   Query values the form sends unchanged, such as the sort.
     */
    public function __construct(
        public string $action,
        public array $fields,
        public array $kept = [],
    ) {
    }
}
