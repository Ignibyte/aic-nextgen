<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One option of a filter's select (#324): the value it sends and the text it
 * shows. An empty value stands for any.
 */
final readonly class FilterOption
{
    /**
     * @param string $value What the form sends when it is chosen.
     * @param string $label What it shows.
     */
    public function __construct(
        public string $value,
        public string $label,
    ) {
    }
}
