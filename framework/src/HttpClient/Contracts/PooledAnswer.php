<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * What one request of a pool got (#305): a response, or why there was none,
 * and how long it took. It is API for the modules that pool.
 *
 * @api
 */
final readonly class PooledAnswer
{
    /**
     * @param RequestInterface       $request      The request.
     * @param ResponseInterface|null $answer       Its response; null when it got none.
     * @param string                 $failure      Why it got none; empty when it got one.
     * @param int                    $microseconds How long it took.
     */
    public function __construct(
        public RequestInterface $request,
        public ?ResponseInterface $answer,
        public string $failure,
        public int $microseconds,
    ) {
    }

    /**
     * @throws NetworkException When the request got no response.
     *
     * @return ResponseInterface The response.
     */
    public function response(): ResponseInterface
    {
        return $this->answer ?? throw new NetworkException($this->request, $this->failure);
    }

    /**
     * @return int The response's status; 0 when it got none.
     */
    public function statusCode(): int
    {
        return $this->answer instanceof ResponseInterface
            ? $this->answer->getStatusCode()
            : 0;
    }
}
