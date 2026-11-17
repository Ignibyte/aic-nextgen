<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

use Override;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * A request got no answer (#305): the host did not resolve, the connection
 * failed, or the time ran out. The message is curl's text for its error code.
 */
final class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    /**
     * @param RequestInterface $request The request that got no answer.
     * @param string           $reason  Why.
     */
    public function __construct(
        private readonly RequestInterface $request,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    /**
     * @return RequestInterface The request that got no answer.
     */
    #[Override]
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
