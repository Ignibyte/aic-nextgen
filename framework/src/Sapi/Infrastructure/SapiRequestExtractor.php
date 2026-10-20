<?php

declare(strict_types=1);

namespace AIC\Sapi\Infrastructure;

use AIC\Http\Domain\InputParser;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Sapi\Contracts\SapiRequest;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function explode;
use function is_string;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function ucwords;

/**
 * Turns one SAPI request into a PSR-7 server request with typed input.
 *
 * Headers come from the `HTTP_*` server params (and the prefix-less
 * `CONTENT_TYPE` and `CONTENT_LENGTH`), the query from `QUERY_STRING`, cookies
 * from the `Cookie` header, and form fields from an urlencoded body. A multipart
 * body is not parsed here: uploads and their checks arrive with #8.
 */
final readonly class SapiRequestExtractor implements RequestExtractor
{
    /**
     * The media type of an urlencoded form body.
     */
    private const string FORM_TYPE = 'application/x-www-form-urlencoded';

    /**
     * @param ServerRequestFactoryInterface $serverRequestFactory Makes the request.
     * @param UriFactoryInterface           $uriFactory           Parses the request URI.
     * @param StreamFactoryInterface        $streamFactory        Makes the body stream.
     * @param InputParser                   $inputParser          Parses the query, the form body and the cookies.
     */
    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private UriFactoryInterface $uriFactory,
        private StreamFactoryInterface $streamFactory,
        private InputParser $inputParser,
    ) {
    }

    /**
     * @param SapiRequest $sapiRequest The request as the SAPI gave it.
     *
     * @throws InvalidArgumentException When the method, the URI or a header is invalid; a
     *                                  MalformedInputException when the query, the form body
     *                                  or a cookie does not fit the field shapes.
     *
     * @return ServerRequestInterface The PSR-7 request.
     */
    #[Override]
    public function extract(SapiRequest $sapiRequest): ServerRequestInterface
    {
        $server = $sapiRequest->server;
        $headers = self::headers($server);
        $request = $this->serverRequestFactory->createServerRequest(
            self::serverString($server, 'REQUEST_METHOD', 'GET'),
            $this->uri($server, $headers['Host'] ?? ''),
            $server,
        );

        foreach ($headers as $name => $value) {
            $request = self::serverRequest($request->withHeader($name, $value));
        }

        $request = self::serverRequest($request->withBody($this->streamFactory->createStream($sapiRequest->body)))
            ->withQueryParams($this->inputParser->fields(self::serverString($server, 'QUERY_STRING', '')))
            ->withCookieParams($this->inputParser->cookies($headers['Cookie'] ?? ''));

        return str_starts_with(strtolower($headers['Content-Type'] ?? ''), self::FORM_TYPE)
            ? $request->withParsedBody($this->inputParser->fields($sapiRequest->body))
            : $request;
    }

    /**
     * @param array<string, scalar> $server The server params.
     * @param string                $host   The Host header; the server name stands in when it is empty.
     *
     * @throws InvalidArgumentException When the URI cannot be parsed.
     *
     * @return UriInterface The request URI.
     */
    private function uri(array $server, string $host): UriInterface
    {
        $https = self::serverString($server, 'HTTPS', '');
        $scheme = $https === '' || $https === 'off'
            ? 'http'
            : 'https';
        $authority = $host !== ''
            ? $host
            : self::serverString($server, 'SERVER_NAME', 'localhost');
        $path = explode('?', self::serverString($server, 'REQUEST_URI', '/'))[0];

        return $this->uriFactory
            ->createUri($scheme . '://' . $authority . $path)
            ->withQuery(self::serverString($server, 'QUERY_STRING', ''));
    }

    /**
     * PSR-7's message withers declare `MessageInterface`; a server request's copy
     * is still a server request, and this says so to elephc, which types the
     * result by the declared return.
     *
     * @param MessageInterface $message A server request's copy.
     *
     * @throws InvalidArgumentException When it is not a server request.
     *
     * @return ServerRequestInterface The copy.
     */
    private static function serverRequest(MessageInterface $message): ServerRequestInterface
    {
        if (!$message instanceof ServerRequestInterface) {
            throw new InvalidArgumentException('A server request wither must return a server request.');
        }

        return $message;
    }

    /**
     * @param array<string, scalar> $server The server params.
     *
     * @return array<string, string> The request headers by canonical name.
     */
    private static function headers(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            $name = self::headerName($key);

            if ($name === '' || !is_string($value)) {
                continue;
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * @param string $key A server param name.
     *
     * @return string The header it carries, such as `Content-Type`; empty when it carries none.
     */
    private static function headerName(string $key): string
    {
        if (str_starts_with($key, 'HTTP_')) {
            return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        }

        return match ($key) {
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            default => '',
        };
    }

    /**
     * @param array<string, scalar> $server  The server params.
     * @param string                $key     The param name.
     * @param string                $default The value when the param is missing or not a string.
     *
     * @return string The param.
     */
    private static function serverString(array $server, string $key, string $default): string
    {
        $value = $server[$key] ?? null;

        return is_string($value)
            ? $value
            : $default;
    }
}
