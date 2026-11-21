<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

/**
 * How a webhook's sender signs a delivery (#321).
 */
enum WebhookScheme: string
{
    /** The hex HMAC-SHA256 of the raw body under the shared secret, with or without a `sha256=` prefix. */
    case HmacSha256 = 'hmac-sha256';

    /**
     * @return non-empty-string The `hash_hmac()` algorithm.
     */
    public function algorithm(): string
    {
        return 'sha256';
    }
}
