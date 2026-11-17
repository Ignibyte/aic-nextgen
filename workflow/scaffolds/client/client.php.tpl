<?php

declare(strict_types=1);

namespace {namespace}\Service;

use AIC\HttpClient\Domain\Upstreams;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * The client of the `{upstream}` upstream, made by bin/aic make:client
 * (#305): its base URL and token are config/app.php's. Each method is one
 * call, taking typed values and answering a typed reply; it is API for the
 * module's controllers and jobs.
 *
 * @api
 */
final readonly class {class}
{
    /** The upstream's name in config/app.php. */
    public const string UPSTREAM = '{upstream}';

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
     * @param string $path A path under the upstream's base URL.
     *
     * @throws InvalidArgumentException When config/app.php names no such upstream, or the path or token is not valid.
     * @throws ClientExceptionInterface When the call gets no answer.
     *
     * @return {reply} Its status and body.
     */
    public function get(string $path): {reply}
    {
        $request = $this->upstreams->upstream(self::UPSTREAM)->request($this->requestFactory, 'GET', $path);
        $response = $this->client->sendRequest($request);

        return new {reply}($response->getStatusCode(), (string) $response->getBody());
    }
}
