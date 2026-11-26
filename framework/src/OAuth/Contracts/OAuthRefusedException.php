<?php

declare(strict_types=1);

namespace AIC\OAuth\Contracts;

use RuntimeException;

/**
 * A request the authorization server refuses (#331), with its RFC 6749 error
 * code (`invalid_request`, `invalid_client`, `invalid_grant`,
 * `unauthorized_client`, `unsupported_grant_type`, `invalid_scope`,
 * `access_denied`, `unsupported_response_type`, `invalid_target`), the
 * description it sends and the status it answers with.
 */
final class OAuthRefusedException extends RuntimeException
{
    /**
     * @param string $error       The error code.
     * @param string $description What was wrong, for the client's developer.
     * @param int    $status      400, or 401 for a client that did not authenticate.
     */
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($error . ': ' . $description);
    }
}
