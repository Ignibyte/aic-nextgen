<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Contracts\RequestPool;
use AIC\HttpClient\Domain\Upstreams;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * What a module needs to call upstreams (#305), as one root of each context:
 * the client, the pool, the factories its requests are made with, and the
 * upstreams config names. The generated wiring reaches each as
 * `outbound()->client()` and so on, so every member is framework API.
 *
 * @api
 */
final readonly class Outbound
{
    /**
     * @param ClientInterface         $client         Sends one request.
     * @param RequestPool             $requestPool    Sends many at once.
     * @param RequestFactoryInterface $requestFactory Makes requests.
     * @param StreamFactoryInterface  $streamFactory  Makes their bodies.
     * @param Upstreams               $upstreams      The upstreams config names.
     */
    public function __construct(
        private ClientInterface $client,
        private RequestPool $requestPool,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private Upstreams $upstreams,
    ) {
    }

    /**
     * @return ClientInterface The client.
     */
    public function client(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @return RequestPool The pool.
     */
    public function pool(): RequestPool
    {
        return $this->requestPool;
    }

    /**
     * @return RequestFactoryInterface The request factory.
     */
    public function requests(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    /**
     * @return StreamFactoryInterface The stream factory.
     */
    public function streams(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * @return Upstreams The upstreams.
     */
    public function upstreams(): Upstreams
    {
        return $this->upstreams;
    }
}
