<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\Http\Application\RequestFactory;
use AIC\Http\Application\StreamFactory;
use AIC\HttpClient\Contracts\OutboundObserver;
use AIC\HttpClient\Domain\Upstreams;

/**
 * Builds a context's `Outbound` (#305): curl's client and pool, observed when
 * an observer is given (the profile, on a profiled request), else as they are.
 */
final readonly class OutboundWiring
{
    /**
     * @param Upstreams             $upstreams        The upstreams config names.
     * @param OutboundObserver|null $outboundObserver Told of each call; null for none.
     *
     * @return Outbound The bundle.
     */
    public function outbound(Upstreams $upstreams, ?OutboundObserver $outboundObserver = null): Outbound
    {
        $curlClient = new CurlClient();
        $curlPool = new CurlPool();
        $streamFactory = new StreamFactory();
        $requestFactory = new RequestFactory(streamFactory: $streamFactory);

        return $outboundObserver instanceof OutboundObserver
            ? new Outbound(
                new ObservedClient($curlClient, $outboundObserver),
                new ObservedPool($curlPool, $outboundObserver),
                $requestFactory,
                $streamFactory,
                $upstreams,
            )
            : new Outbound($curlClient, $curlPool, $requestFactory, $streamFactory, $upstreams);
    }
}
