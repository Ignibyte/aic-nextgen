<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Contracts\OutboundCall;
use AIC\HttpClient\Contracts\OutboundObserver;
use AIC\Kernel\Domain\Monotonic;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A client that tells an observer of each call (#305): the profile, when the
 * profiler records. A call that throws is told with status 0, then thrown on.
 */
final readonly class ObservedClient implements ClientInterface
{
    /**
     * @param ClientInterface  $client           The client that sends.
     * @param OutboundObserver $outboundObserver Told of each call.
     * @param Monotonic        $monotonic        Times it.
     */
    public function __construct(
        private ClientInterface $client,
        private OutboundObserver $outboundObserver,
        private Monotonic $monotonic = new Monotonic(),
    ) {
    }

    /**
     * @param RequestInterface $request The request.
     *
     * @throws ClientExceptionInterface When the client throws.
     *
     * @return ResponseInterface The client's response.
     */
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $started = $this->monotonic->microseconds();

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $clientException) {
            $this->told($request, 0, $started);

            throw $clientException;
        }

        $this->told($request, $response->getStatusCode(), $started);

        return $response;
    }

    /**
     * @param RequestInterface $request The request.
     * @param int              $status  Its response's status; 0 for none.
     * @param int              $started When it was sent.
     */
    private function told(RequestInterface $request, int $status, int $started): void
    {
        $microseconds = $this->monotonic->microseconds() - $started;
        $this->outboundObserver->called(
            new OutboundCall($request->getMethod(), (string) $request->getUri(), $status, $microseconds),
        );
    }
}
