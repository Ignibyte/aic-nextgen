<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\HttpClient;
use Gates\Domain\HttpExchange;
use Override;

use function explode;
use function file_get_contents;
use function http_clear_last_response_headers;
use function http_get_last_response_headers;
use function is_array;
use function mb_strtolower;
use function mb_trim;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function stream_context_create;

/**
 * An HTTP client over PHP's `http://` stream wrapper: no redirect followed,
 * error statuses read like any other, a ten-second timeout. A refused
 * connection is an answer with status 0; its warning is caught, not printed.
 */
final readonly class StreamHttpClient implements HttpClient
{
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
        $lines = 'Connection: close' . ($form === '' ? '' : "\r\nContent-Type: application/x-www-form-urlencoded")
            . ($header === '' ? '' : "\r\n" . $header);
        $context = stream_context_create(['http' => [
            'content' => $form,
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

        $headers = http_get_last_response_headers();

        return self::exchange(is_array($headers) ? $headers : [], $body === false ? '' : $body);
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
            if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];

                continue;
            }

            $fields = self::withField($fields, $header);
        }

        return new HttpExchange($status, $fields['content-type'] ?? '', $fields['location'] ?? '', $body);
    }

    /**
     * @param array<string, string> $fields The header fields so far, by lower-case name.
     * @param string                $line   One header line.
     *
     * @return array<string, string> The fields with this line's, the last of a name winning.
     */
    private static function withField(array $fields, string $line): array
    {
        if (!str_contains($line, ':')) {
            return $fields;
        }

        [$name, $value] = explode(':', $line, 2);
        $fields[mb_strtolower(mb_trim($name))] = mb_trim($value);

        return $fields;
    }
}
