<?php

declare(strict_types=1);

namespace App\Content\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The content pages' list, from ContentPages.html.php: a link to each page,
 * or a line saying there are none.
 */
final readonly class ContentPages implements Component
{
    /**
     * @param list<KeyedComponent> $links A link to each page, keyed by its slug, in order.
     */
    public function __construct(
        public array $links,
    ) {
    }
}
