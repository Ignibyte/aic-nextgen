<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * Terms and their details (#341), such as a row's fields on its detail page:
 * a `dl` with the class `aic-description-list`, each pair in a `div`.
 */
final readonly class DescriptionList implements Component
{
    /**
     * @param list<DescriptionItem> $items The pairs, in order.
     */
    public function __construct(
        public array $items,
    ) {
    }
}
