<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SiteProbe;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Domain\RenderedAnswer;
use Override;

use function array_key_exists;
use function array_map;
use function file_get_contents;
use function http_get_last_response_headers;
use function restore_error_handler;
use function set_error_handler;
use function stream_context_create;
use function strtolower;
use function trim;

/**
 * Sends the rendered check's GETs (#101) through PHP's HTTP stream wrapper:
 * no redirect followed, an error status read like any other, and no byte of
 * the body read, so a stream route answers without being drained.
 */
final readonly class HttpSiteProbe implements SiteProbe
{
    use RegexCaptures;

    /**
     * @param string $url An absolute URL.
     *
     * @return RenderedAnswer|null Its status and content type; null when nothing answered.
     */
    #[Override]
    public function get(string $url): ?RenderedAnswer
    {
        $context = stream_context_create(['http' => ['follow_location' => 0, 'ignore_errors' => true, 'method' => 'GET', 'timeout' => 10]]);
        set_error_handler(static fn (): bool => true);

        try {
            file_get_contents($url, false, $context, 0, 0);
        } finally {
            restore_error_handler();
        }

        $headers = [];

        foreach (array_map(static fn (string $header): string => $header, http_get_last_response_headers() ?? []) as $header) {
            $headers[] = $header;
        }

        return $this->answer($headers);
    }

    /**
     * @param list<string> $headers The response's header lines, the status line first; none when nothing answered.
     *
     * @return RenderedAnswer|null The answer; null when there is no status line.
     */
    private function answer(array $headers): ?RenderedAnswer
    {
        $status = 0;
        $contentType = '';
        $location = '';

        foreach ($headers as $header) {
            $matches = self::regexMatch('#^HTTP/\\S+\\s+(\\d{3})#', $header);
            $status = array_key_exists(1, $matches)
                ? (int) $matches[1]
                : $status;
            $contentType = $this->field('content-type', $header) ?? $contentType;
            $location = $this->field('location', $header) ?? $location;
        }

        return $status === 0
            ? null
            : new RenderedAnswer($status, strtolower($contentType), $location);
    }

    /**
     * @param non-empty-string $name   A header's name, lower case.
     * @param string           $header A header line.
     *
     * @return string|null Its value, trimmed, when the line is that header; null otherwise.
     */
    private function field(string $name, string $header): ?string
    {
        $matches = self::regexMatch('/^' . $name . ':(.*)$/i', $header);

        return array_key_exists(1, $matches)
            ? trim($matches[1])
            : null;
    }
}
