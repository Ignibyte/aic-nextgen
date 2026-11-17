<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Domain\RetryPolicy;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function usleep;

/**
 * Retries, said out loud (#305): a client that sends a request again after a
 * network failure or a status its policy lists, waiting longer each time, up
 * to the policy's attempts. The last attempt's response or exception is the
 * answer. No AIC client retries unless it is wrapped in this one.
 */
final readonly class RetryingClient implements ClientInterface
{
    /**
     * @param ClientInterface $client      The client that sends.
     * @param RetryPolicy     $retryPolicy When, and how often, to send again.
     */
    public function __construct(
        private ClientInterface $client,
        private RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {
    }

    /**
     * @param RequestInterface $request The request.
     *
     * @throws ClientExceptionInterface When the last attempt throws, or an earlier one throws anything but a network failure.
     *
     * @return ResponseInterface The first response the policy does not retry, or the last attempt's.
     */
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 1; $attempt < $this->retryPolicy->attempts; $attempt += 1) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (NetworkExceptionInterface) {
                usleep($this->retryPolicy->delay($attempt));

                continue;
            }

            if (!$this->retryPolicy->retries($response->getStatusCode())) {
                return $response;
            }

            usleep($this->retryPolicy->delay($attempt));
        }

        return $this->client->sendRequest($request);
    }
}
