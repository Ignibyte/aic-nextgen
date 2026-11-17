<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

use Override;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * A request that cannot be sent (#305): its URI is not an absolute `http` or
 * `https` one, or curl refused it.
 */
final class RequestException extends RuntimeException implements RequestExceptionInterface
{
    /**
     * @param RequestInterface $request The request that cannot be sent.
     * @param string           $reason  Why.
     */
    public function __construct(
        private readonly RequestInterface $request,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    /**
     * @return RequestInterface The request that cannot be sent.
     */
    #[Override]
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
