<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A layout's tracked assets (Tessera protocol 9): the site's stylesheet and
 * Tessera's script, each `data-track`, so a visit to a page with others loads
 * it in full.
 */
final readonly class TrackedAssets implements Component
{
    /**
     * @param string $stylesheet The stylesheet's path, such as `/assets/aic.css`.
     * @param string $script     The script's path, loaded deferred.
     */
    public function __construct(
        public string $stylesheet,
        public string $script,
    ) {
    }
}
