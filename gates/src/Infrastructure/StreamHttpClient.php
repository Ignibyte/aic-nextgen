<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\HttpClient;
use Gates\Domain\HttpExchange;
use Gates\Domain\RegexCaptures;
use Gates\Domain\StringValues;
use Override;

use function array_key_exists;
use function basename;
use function explode;
use function file_get_contents;
use function http_clear_last_response_headers;
use function http_get_last_response_headers;
use function mb_strtolower;
use function mb_trim;
use function restore_error_handler;
use function set_error_handler;
use function str_starts_with;
use function stream_context_create;
use function strlen;
use function substr;
use function urldecode;

/**
 * An HTTP client over PHP's `http://` stream wrapper: no redirect followed,
 * error statuses read like any other, a ten-second timeout. A refused
 * connection is an answer with status 0; its warning is caught, not printed.
 */
final readonly class StreamHttpClient implements HttpClient
{
    use RegexCaptures;
    use StringValues;

    /**
     * The prefix that makes a step's form a multipart body (#44).
     */
    private const string MULTIPART = 'multipart:';

    /**
     * The multipart boundary.
     */
    private const string BOUNDARY = 'aic-gate-boundary';

    /**
     * @param string $origin `http://host:port`.
     * @param string $method The method.
     * @param string $path   The path.
     * @param string $form   A form-encoded body; empty for none.
     * @param string $header One extra request header line; empty for none.
     *
     * @return HttpExchange The answer; status 0 when there was none.
     */
    #[Override]
    public function send(string $origin, string $method, string $path, string $form, string $header): HttpExchange
    {
        [$contentType, $content] = self::body($form);
        $lines = 'Connection: close' . ($form === '' ? '' : "\r\nContent-Type: " . $contentType)
            . ($header === '' ? '' : "\r\n" . $header);
        $context = stream_context_create(['http' => [
            'content' => $content,
            'follow_location' => 0,
            'header' => $lines,
            'ignore_errors' => true,
            'method' => $method,
            'timeout' => 10,
        ]]);
        http_clear_last_response_headers();
        set_error_handler(static fn (): bool => true);

        try {
            $body = file_get_contents($origin . $path, false, $context);
        } finally {
            restore_error_handler();
        }

        return self::exchange(self::headers(), $body === false ? '' : $body);
    }

    /**
     * @return list<string> The wrapper's response header lines.
     */
    private static function headers(): array
    {
        return self::strings(http_get_last_response_headers() ?? []);
    }

    /**
     * @param list<string> $headers The status line and the header lines.
     * @param string       $body    The body.
     *
     * @return HttpExchange The answer.
     */
    private static function exchange(array $headers, string $body): HttpExchange
    {
        $status = 0;
        $fields = [];

        foreach ($headers as $header) {
            $match = self::regexMatch('#^HTTP/\\S+\\s+(\\d{3})#', $header);

            if (array_key_exists(1, $match)) {
                $status = (int) $match[1];

                continue;
            }

            $fields = self::withField($fields, $header);
        }

        $cookie = explode(';', $fields['set-cookie'] ?? '', 2)[0];

        $location = $fields['location'] ?? $fields['tessera-location'] ?? '';

        return new HttpExchange($status, $fields['content-type'] ?? '', $location, $body, $cookie, self::compared($fields));
    }

    /**
     * @param array<string, string> $fields The header fields so far, by lower-case name.
     * @param string                $line   One header line.
     *
     * @return array<string, string> The fields with this line's, the last of a name winning.
     */
    private static function withField(array $fields, string $line): array
    {
        $parts = explode(':', $line, 2);

        if (!array_key_exists(1, $parts)) {
            return $fields;
        }

        $fields[mb_strtolower(mb_trim($parts[0]))] = mb_trim($parts[1]);

        return $fields;
    }

    /**
     * @param string $fields `name=value` pairs joined by `&`, urlencoded; a value `@path` uploads that file.
     *
     * @return string The multipart body.
     */
    private static function multipart(string $fields): string
    {
        $body = '';

        foreach (explode('&', $fields) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + ['', ''];
            $body .= self::part(urldecode($name), urldecode($value));
        }

        return $body . '--' . self::BOUNDARY . "--\r\n";
    }

    /**
     * @param string $name  A field name.
     * @param string $value Its value; `@path` uploads that file.
     *
     * @return string Its multipart part.
     */
    private static function part(string $name, string $value): string
    {
        $file = str_starts_with($value, '@')
            ? substr($value, 1)
            : '';
        $disposition = 'Content-Disposition: form-data; name="' . $name . '"'
            . ($file === '' ? '' : '; filename="' . basename($file) . "\"\r\nContent-Type: application/octet-stream");

        return '--' . self::BOUNDARY . "\r\n" . $disposition . "\r\n\r\n"
            . ($file === '' ? $value : (string) file_get_contents($file)) . "\r\n";
    }

    /**
     * @param string $form A step's form: urlencoded, or `multipart:` and urlencoded pairs.
     *
     * @return array{string, string} The content type and the body.
     */
    private static function body(string $form): array
    {
        return str_starts_with($form, self::MULTIPART)
            ? ['multipart/form-data; boundary=' . self::BOUNDARY, self::multipart(substr($form, strlen(self::MULTIPART)))]
            : ['application/x-www-form-urlencoded', $form];
    }

    /**
     * @param array<string, string> $fields Every header field, by lower-case name.
     *
     * @return array<string, string> The ones gate:23 compares (#173): the protocol's and caching's.
     */
    private static function compared(array $fields): array
    {
        $compared = [];

        foreach ($fields as $name => $value) {
            if (!str_starts_with($name, 'tessera-') && $name !== 'vary' && $name !== 'cache-control') {
                continue;
            }

            $compared[$name] = $value;
        }

        return $compared;
    }
}
