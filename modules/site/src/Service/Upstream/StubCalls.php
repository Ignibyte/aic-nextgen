<?php

declare(strict_types=1);

namespace App\Site\Service\Upstream;

use AIC\HttpClient\Application\RetryingClient;
use AIC\HttpClient\Contracts\PooledAnswer;
use AIC\HttpClient\Contracts\PooledRequest;
use AIC\HttpClient\Contracts\RequestPool;
use AIC\HttpClient\Domain\Upstreams;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * GET /upstream's calls to gate:23's stub upstream (#305): one through the
 * made client, two at once through the pool (one given 100 ms for an answer
 * that takes 500), and one retried until its 503 stands.
 */
final readonly class StubCalls
{
    /**
     * @param StubClient              $stubClient     The stub's typed client.
     * @param RequestPool             $requestPool    Sends the pair at once.
     * @param RequestFactoryInterface $requestFactory Makes the pooled and retried requests.
     * @param Upstreams               $upstreams      Where the stub is.
     * @param RetryingClient          $retryingClient Retries the failing call.
     */
    public function __construct(
        private StubClient $stubClient,
        private RequestPool $requestPool,
        private RequestFactoryInterface $requestFactory,
        private Upstreams $upstreams,
        private RetryingClient $retryingClient,
    ) {
    }

    /**
     * @throws InvalidArgumentException When config/app.php names no stub upstream.
     *
     * @return UpstreamBody What each call got; zeros when the stub is not there.
     */
    public function body(): UpstreamBody
    {
        try {
            $echo = $this->stubClient->echo();
            $stub = $this->upstreams->upstream(StubClient::UPSTREAM);
            $answers = $this->requestPool->send([
                'fast' => new PooledRequest($this->requestFactory->createRequest('GET', $stub->url('/status/201'))),
                'slow' => new PooledRequest($this->requestFactory->createRequest('GET', $stub->url('/slow')), 100),
            ]);
            $failing = $this->requestFactory->createRequest('GET', $stub->url('/status/503'));
            $retried = $this->retryingClient->sendRequest($failing);
        } catch (ClientExceptionInterface) {
            return new UpstreamBody(0, '', 0, 0, 0);
        }

        return new UpstreamBody(
            $echo->status,
            $echo->body,
            self::status($answers, 'fast'),
            self::status($answers, 'slow'),
            $retried->getStatusCode(),
        );
    }

    /**
     * @param array<string, PooledAnswer> $answers The pool's answers.
     * @param string                      $name    One request's name.
     *
     * @return int Its status; 0 when it got none.
     */
    private static function status(array $answers, string $name): int
    {
        $answer = $answers[$name] ?? null;

        return $answer instanceof PooledAnswer
            ? $answer->statusCode()
            : 0;
    }
}
