<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One pair of a `DescriptionList` (#341).
 */
final readonly class DescriptionItem
{
    /**
     * @param string $term    What it names, the `dt`.
     * @param string $details Its value, the `dd`; empty shows an empty `dd`.
     */
    public function __construct(
        public string $term,
        public string $details,
    ) {
    }
}
