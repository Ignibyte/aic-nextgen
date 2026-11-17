<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Contracts\OutboundCall;
use AIC\HttpClient\Contracts\OutboundObserver;
use AIC\HttpClient\Contracts\PooledAnswer;
use AIC\HttpClient\Contracts\PooledRequest;
use AIC\HttpClient\Contracts\RequestException;
use AIC\HttpClient\Contracts\RequestPool;
use Override;

/**
 * A pool that tells an observer of each call it made (#305), with the time
 * curl gave that call.
 */
final readonly class ObservedPool implements RequestPool
{
    /**
     * @param RequestPool      $requestPool      The pool that sends.
     * @param OutboundObserver $outboundObserver Told of each call.
     */
    public function __construct(
        private RequestPool $requestPool,
        private OutboundObserver $outboundObserver,
    ) {
    }

    /**
     * @param array<string, PooledRequest> $requests The requests, each under a name.
     *
     * @throws RequestException When a request cannot be sent at all; none is then sent.
     *
     * @return array<string, PooledAnswer> What each got, under its name, in the order given.
     */
    #[Override]
    public function send(array $requests): array
    {
        $answers = $this->requestPool->send($requests);

        foreach ($answers as $answer) {
            $this->told($answer);
        }

        return $answers;
    }

    /**
     * Tells the observer of one call. A typed parameter, not the loop's element, so the compiled site reads its
     * members as `PooledAnswer`'s alone.
     *
     * @param PooledAnswer $pooledAnswer What one request got.
     */
    private function told(PooledAnswer $pooledAnswer): void
    {
        $request = $pooledAnswer->request;
        $url = (string) $request->getUri();
        $this->outboundObserver->called(
            new OutboundCall($request->getMethod(), $url, $pooledAnswer->statusCode(), $pooledAnswer->microseconds),
        );
    }
}
