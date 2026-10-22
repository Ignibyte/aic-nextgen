<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SiteProbe;
use AIC\Workflow\Domain\RenderedAnswer;
use Override;

use function file_get_contents;
use function http_get_last_response_headers;
use function preg_match;
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

        return $this->answer(http_get_last_response_headers() ?? []);
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

        foreach ($headers as $header) {
            $status = preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $header, $matches) === 1
                ? (int) $matches[1]
                : $status;
            $contentType = $this->contentType($header) ?? $contentType;
        }

        return $status === 0
            ? null
            : new RenderedAnswer($status, $contentType);
    }

    /**
     * @param string $header A header line.
     *
     * @return string|null Its value, lowercased, when it is the content type; null otherwise.
     */
    private function contentType(string $header): ?string
    {
        return preg_match('/^content-type:(.*)$/i', $header, $matches) === 1
            ? strtolower(trim($matches[1]))
            : null;
    }
}
