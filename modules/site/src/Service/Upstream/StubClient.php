<?php

declare(strict_types=1);

namespace App\Site\Service\Upstream;

use AIC\HttpClient\Domain\Upstreams;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * The client of the `stub` upstream, made by bin/aic make:client
 * (#305): its base URL and token are config/app.php's, and its one call is
 * the stub's echo, which StubCalls makes for GET /upstream.
 */
final readonly class StubClient
{
    /** The upstream's name in config/app.php. */
    public const string UPSTREAM = 'stub';

    /**
     * @param ClientInterface         $client         Sends the calls.
     * @param RequestFactoryInterface $requestFactory Makes the requests.
     * @param Upstreams               $upstreams      Where the upstream is, and its token.
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private Upstreams $upstreams,
    ) {
    }

    /**
     * @throws InvalidArgumentException When config/app.php names no stub upstream, or its token is not valid.
     * @throws ClientExceptionInterface When the call gets no answer.
     *
     * @return StubReply What the stub echoed of the call: its method, and whether the token reached it.
     */
    public function echo(): StubReply
    {
        $upstream = $this->upstreams->upstream(self::UPSTREAM);
        $response = $this->client->sendRequest($upstream->request($this->requestFactory, 'GET', '/echo'));
        $body = (string) $response->getBody();

        return new StubReply($response->getStatusCode(), $body);
    }
}
