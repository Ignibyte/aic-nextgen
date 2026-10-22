<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * Trusted markup written as it is (#99), the one way round the Renderer's
 * escaping: a sanitized CMS body, say. It has no template; the Renderer writes
 * its markup in place. A class may build one only where `config/raw-html.php`
 * grants it, and gate:27 counts and lists every use against those grants.
 *
 * @api
 */
final readonly class RawHtml implements Component
{
    /**
     * @param string $trustedMarkup Markup that is safe to send as it is: never input as it arrived.
     */
    public function __construct(
        public string $trustedMarkup,
    ) {
    }
}
