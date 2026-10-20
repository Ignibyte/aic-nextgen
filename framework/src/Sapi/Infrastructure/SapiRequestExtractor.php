<?php

declare(strict_types=1);

namespace AIC\Sapi\Infrastructure;

use AIC\Http\Domain\InputParser;
use AIC\Http\Domain\UploadedFile;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Sapi\Contracts\SapiRequest;
use AIC\Sapi\Contracts\SapiUpload;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function array_map;
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
 * post's fields and uploads come parsed by the SAPI (#44), and become the
 * parsed body and the uploaded files.
 */
final readonly class SapiRequestExtractor implements RequestExtractor
{
    /**
     * The media type of an urlencoded form body.
     */
    private const string FORM_TYPE = 'application/x-www-form-urlencoded';

    /**
     * The media type of a multipart form body, whose fields and uploads the SAPI parsed.
     */
    private const string MULTIPART_TYPE = 'multipart/form-data';

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

        $contentType = strtolower($headers['Content-Type'] ?? '');

        if (str_starts_with($contentType, self::MULTIPART_TYPE)) {
            return $request->withParsedBody($sapiRequest->fields)->withUploadedFiles($this->uploadedFiles($sapiRequest->uploads));
        }

        return str_starts_with($contentType, self::FORM_TYPE)
            ? $request->withParsedBody($this->inputParser->fields($sapiRequest->body))
            : $request;
    }

    /**
     * @param list<SapiUpload> $sapiUploads The uploads the SAPI gave.
     *
     * @return array<string, UploadedFileInterface> One uploaded file per field.
     */
    private function uploadedFiles(array $sapiUploads): array
    {
        $fields = array_map(static fn (SapiUpload $sapiUpload): string => $sapiUpload->field, $sapiUploads);
        $files = array_map($this->uploadedFile(...), $sapiUploads);
        $byField = [];

        foreach ($fields as $index => $field) {
            $byField[$field] = $files[$index];
        }

        return $byField;
    }

    /**
     * @param SapiUpload $sapiUpload An upload the SAPI gave.
     *
     * @return UploadedFileInterface It, with a stream over its temporary file; an empty one when it failed.
     */
    private function uploadedFile(SapiUpload $sapiUpload): UploadedFileInterface
    {
        $stream = $sapiUpload->error === 0 && $sapiUpload->path !== ''
            ? $this->streamFactory->createStreamFromFile($sapiUpload->path)
            : $this->streamFactory->createStream('');

        return new UploadedFile(
            $sapiUpload->path,
            $stream,
            $sapiUpload->size,
            $sapiUpload->error,
            $sapiUpload->clientName,
            $sapiUpload->clientType,
        );
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
