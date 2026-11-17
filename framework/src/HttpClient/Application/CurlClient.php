<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Contracts\AnswerException;
use AIC\HttpClient\Contracts\NetworkException;
use AIC\HttpClient\Contracts\RequestException;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function curl_errno;
use function curl_exec;
use function is_string;

/**
 * The PSR-18 client (#305): one request at a time over ext-curl, which the
 * compiled site links natively. Any status is a response; only a request that
 * cannot be sent or gets no answer throws. Nothing is retried here: wrap it in
 * `RetryingClient` to retry.
 */
final readonly class CurlClient implements ClientInterface
{
    /**
     * @param CurlTransfer $curlTransfer Turns a request into a handle and its answer into a response.
     */
    public function __construct(
        private CurlTransfer $curlTransfer = new CurlTransfer(),
    ) {
    }

    /**
     * @param RequestInterface $request The request.
     *
     * @throws RequestException When it cannot be sent.
     * @throws NetworkException When it got no answer.
     * @throws AnswerException  When the answer is not an HTTP response.
     *
     * @return ResponseInterface The response, whatever its status.
     */
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = $this->curlTransfer->handle($request, 0);
        $raw = curl_exec($handle);

        if (!is_string($raw)) {
            throw new NetworkException($request, $this->curlTransfer->failure(curl_errno($handle)));
        }

        return $this->curlTransfer->response($raw);
    }
}
