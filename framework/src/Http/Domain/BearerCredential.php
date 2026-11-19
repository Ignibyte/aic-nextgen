<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use Psr\Http\Message\ServerRequestInterface;

use function strlen;
use function strncasecmp;
use function substr;
use function trim;

/**
 * The bearer token a request carries in its `Authorization` header
 * (RFC 6750, #313). A bearer request is judged by its token alone: it skips
 * the CSRF check and the sign-in redirect, and its actor is the token's.
 */
final readonly class BearerCredential
{
    /**
     * The scheme, followed by one space.
     */
    private const string SCHEME = 'Bearer ';

    /**
     * @param ServerRequestInterface $serverRequest A request.
     *
     * @return bool Whether its `Authorization` header names the bearer scheme, with or without a token.
     */
    public function present(ServerRequestInterface $serverRequest): bool
    {
        $header = $serverRequest->getHeaderLine('Authorization');

        return trim($header) === 'Bearer' || strncasecmp($header, self::SCHEME, strlen(self::SCHEME)) === 0;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request.
     *
     * @return string The token after the scheme; empty when there is none.
     */
    public function token(ServerRequestInterface $serverRequest): string
    {
        return $this->present($serverRequest)
            ? trim(substr($serverRequest->getHeaderLine('Authorization'), strlen(self::SCHEME) - 1))
            : '';
    }
}
