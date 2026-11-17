<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\Http\Application\ResponseFactory;
use AIC\Http\Application\StreamFactory;
use AIC\HttpClient\Contracts\AnswerException;
use AIC\HttpClient\Contracts\RequestException;
use CurlHandle;
use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function curl_init;
use function curl_setopt;
use function curl_strerror;
use function explode;
use function implode;
use function min;
use function preg_match;
use function strpos;
use function substr;
use function trim;

use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_NOBODY;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;

/**
 * One request as a curl easy handle, and its answer back as a PSR-7 response
 * (#305). The answer is read whole, its headers included (`CURLOPT_HEADER`),
 * and split at its first blank line, an interim 1xx answer skipped: a header
 * callback is a closure, which does not bind natively. Redirects are not
 * followed, and no `Expect` header asks for a 100.
 */
final readonly class CurlTransfer
{
    /** Milliseconds a connection may take, at most. */
    private const int CONNECT_TIMEOUT_MS = 5_000;

    /** Milliseconds a request may take in all, unless it says otherwise. */
    private const int TIMEOUT_MS = 30_000;

    /** A status line: the version, the status and the reason. */
    private const string STATUS_LINE = '#^HTTP/(\\d(?:\\.\\d)?) (\\d{3})(?: (.*))?$#';

    /**
     * @param ResponseFactoryInterface $responseFactory Makes the responses.
     * @param StreamFactoryInterface   $streamFactory   Makes their bodies.
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory = new ResponseFactory(new StreamFactory()),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
    ) {
    }

    /**
     * @param RequestInterface $request   The request.
     * @param int              $timeoutMs Milliseconds it may take in all; 0 for the default.
     *
     * @throws RequestException When its URI is not an absolute http or https one, or curl refuses it.
     *
     * @return CurlHandle Its easy handle, ready to run.
     */
    public function handle(RequestInterface $request, int $timeoutMs): CurlHandle
    {
        $uri = $request->getUri();

        if (($uri->getScheme() !== 'http' && $uri->getScheme() !== 'https') || $uri->getHost() === '') {
            throw new RequestException($request, 'Only an absolute http or https URI can be sent: ' . $uri->__toString() . '.');
        }

        $handle = curl_init((string) $uri);

        if ($handle === false) {
            throw new RequestException($request, 'curl could not start the request.');
        }

        $this->options($handle, $request, $timeoutMs > 0 ? $timeoutMs : self::TIMEOUT_MS);

        return $handle;
    }

    /**
     * @param string $raw What a handle read: the headers, a blank line, then the body.
     *
     * @throws AnswerException When the answer has no status line, or a header or status a message cannot hold.
     *
     * @return ResponseInterface The response.
     */
    public function response(string $raw): ResponseInterface
    {
        try {
            return $this->parsed($raw);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $reason = 'The upstream answered with what a message cannot hold: ' . $invalidArgumentException->getMessage();

            throw new AnswerException($reason, 0, $invalidArgumentException);
        }
    }

    /**
     * @param int $code A curl error code.
     *
     * @return string curl's text for it.
     */
    public function failure(int $code): string
    {
        return curl_strerror($code) ?? 'curl error ' . $code;
    }

    /**
     * @param CurlHandle       $curlHandle The handle.
     * @param RequestInterface $request    Its request.
     * @param int              $timeoutMs  Milliseconds it may take in all.
     */
    private function options(CurlHandle $curlHandle, RequestInterface $request, int $timeoutMs): void
    {
        $method = $request->getMethod();
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $method === '' ? 'GET' : $method);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $this->headers($request));
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT_MS, min(self::CONNECT_TIMEOUT_MS, $timeoutMs));
        curl_setopt($curlHandle, CURLOPT_TIMEOUT_MS, $timeoutMs);
        $this->body($curlHandle, $request);
    }

    /**
     * @param string $raw What a handle read.
     *
     * @throws AnswerException          When the answer has no status line.
     * @throws InvalidArgumentException When a header or the status is one a message cannot hold.
     *
     * @return ResponseInterface The final response; an interim 1xx answer before it is skipped.
     */
    private function parsed(string $raw): ResponseInterface
    {
        [$head, $body] = self::split($raw);
        $lines = explode("\r\n", $head);
        $response = $this->status($lines[0]);

        return $response->getStatusCode() < 200
            ? $this->parsed($body)
            : self::answer($this->withHeaderLines($response, $lines)->withBody($this->streamFactory->createStream($body)));
    }

    /**
     * @param ResponseInterface $response The response, its status read.
     * @param list<string>      $lines    The head's lines, the status line first.
     *
     * @throws InvalidArgumentException When a header is not one a message can hold.
     *
     * @return ResponseInterface It with every header line after the status line.
     */
    private function withHeaderLines(ResponseInterface $response, array $lines): ResponseInterface
    {
        foreach ($lines as $position => $line) {
            $response = $position === 0
                ? $response
                : $this->header($response, $line);
        }

        return $response;
    }

    /**
     * @param RequestInterface $request The request.
     *
     * @return list<string> Its headers as curl takes them, and an empty `Expect` so no body waits for a 100.
     */
    private function headers(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }

        return [...$headers, 'Expect:'];
    }

    /**
     * @param CurlHandle       $curlHandle The handle.
     * @param RequestInterface $request    The request: a HEAD reads no body; any other sends its body when it has one.
     */
    private function body(CurlHandle $curlHandle, RequestInterface $request): void
    {
        $body = (string) $request->getBody();

        if ($request->getMethod() === 'HEAD') {
            curl_setopt($curlHandle, CURLOPT_NOBODY, true);
        } elseif ($body !== '') {
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $body);
        }
    }

    /**
     * @param string $line The answer's status line.
     *
     * @throws AnswerException          When it is not one.
     * @throws InvalidArgumentException When its status or version is one a message cannot hold.
     *
     * @return ResponseInterface A response with its status, reason and version.
     */
    private function status(string $line): ResponseInterface
    {
        $matches = [];
        $found = preg_match(self::STATUS_LINE, $line, $matches) === 1;
        $status = (int) ($matches[2] ?? '0');
        $version = $matches[1] ?? '1.1';

        if (!$found || $status < 100) {
            throw new AnswerException('The upstream answered with no HTTP status line: ' . $line);
        }

        return self::answer($this->responseFactory->createResponse($status, $matches[3] ?? '')->withProtocolVersion($version));
    }

    /**
     * @param ResponseInterface $response The response so far.
     * @param string            $line     One header line.
     *
     * @throws InvalidArgumentException When the header is not one a message can hold.
     *
     * @return ResponseInterface The response with it; a line with no colon is skipped.
     */
    private function header(ResponseInterface $response, string $line): ResponseInterface
    {
        $colon = strpos($line, ':');

        return $colon === false
            ? $response
            : self::answer($response->withAddedHeader(substr($line, 0, $colon), trim(substr($line, $colon + 1))));
    }

    /**
     * @param string $raw What a handle read.
     *
     * @return array{string, string} The head, before the first blank line, and the rest.
     */
    private static function split(string $raw): array
    {
        $end = strpos($raw, "\r\n\r\n");

        return $end === false
            ? [$raw, '']
            : [substr($raw, 0, $end), substr($raw, $end + 4)];
    }

    /**
     * @param MessageInterface $message A response's copy, which a PSR-7 wither types as the message it is.
     *
     * @throws InvalidArgumentException When it is not a response.
     *
     * @return ResponseInterface The copy, as a response.
     */
    private static function answer(MessageInterface $message): ResponseInterface
    {
        if (!$message instanceof ResponseInterface) {
            throw new InvalidArgumentException('A response wither must return a response.');
        }

        return $message;
    }
}
