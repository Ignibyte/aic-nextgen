<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One field of a `FilterBar` (#324): a search box, or a select when it has
 * options, holding the value the list is filtered by now.
 */
final readonly class FilterField
{
    /**
     * @param string             $name    Its query-string name.
     * @param string             $label   Its label.
     * @param string             $value   The value the list is filtered by now; empty for none.
     * @param list<FilterOption> $options A select's options, in order; empty for a search box.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $value = '',
        public array $options = [],
    ) {
    }
}
