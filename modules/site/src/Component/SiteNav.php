<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The site's navigation, from site-nav.tpl.php: a link to each demo, the
 * current one marked.
 */
final readonly class SiteNav implements Component
{
    /**
     * @param list<KeyedComponent> $links A link to each demo, keyed by its label, in order.
     */
    public function __construct(
        public array $links,
    ) {
    }
}
