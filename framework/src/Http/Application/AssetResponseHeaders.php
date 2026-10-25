<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Domain\HttpDate;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/** Emit public validators and cache policy only for an already verified representation. */
final readonly class AssetResponseHeaders
{
    /** @param ResponseBuilder $responseBuilder Adds immutable response headers. */
    public function __construct(private ResponseBuilder $responseBuilder)
    {
    }

    /**
     * @param ResponseInterface $response    Its already selected status and body.
     * @param PublicAsset       $publicAsset The verified catalog record.
     * @param int               $modified    The same-handle modification time, clamped to Date.
     * @param int               $now         The one response origination time.
     * @param bool              $canonical   Whether the exact fingerprint URL was requested.
     *
     * @throws InvalidArgumentException When response metadata is invalid.
     * @throws RuntimeException         When the selected byte body's size cannot be measured.
     *
     * @return ResponseInterface Its public metadata, exact length and no application variation.
     */
    public function apply(
        ResponseInterface $response,
        PublicAsset $publicAsset,
        int $modified,
        int $now,
        bool $canonical,
    ): ResponseInterface {
        $httpDate = new HttpDate();
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => $this->cache($response->getStatusCode(), $canonical),
            'Date' => $httpDate->format($now),
            'ETag' => '"' . $publicAsset->sha256 . '"',
            'Last-Modified' => $httpDate->format($modified),
            'X-Content-Type-Options' => 'nosniff',
        ];

        foreach ($headers as $name => $value) {
            $response = $this->responseBuilder->withHeader($response, $name, $value);
        }

        return $this->length($response);
    }

    /**
     * @param int  $status    The selected public response status.
     * @param bool $canonical Whether the exact fingerprint URL was requested.
     *
     * @return string The canonical, alias or failure cache policy.
     */
    private function cache(int $status, bool $canonical): string
    {
        if ($status >= 400) {
            return 'no-store';
        }

        return $canonical
            ? 'public, max-age=31536000, immutable'
            : 'public, no-cache';
    }

    /**
     * @param ResponseInterface $response The response with public metadata.
     *
     * @throws InvalidArgumentException When a response header is invalid.
     * @throws RuntimeException         When the selected body's length is unknown.
     *
     * @return ResponseInterface The response with an exact body length, preserving HEAD metadata and omitting 304 length.
     */
    private function length(ResponseInterface $response): ResponseInterface
    {
        if ($response->getStatusCode() === 304 || $response->hasHeader('Content-Length')) {
            return $response;
        }

        $size = $response->getBody()->getSize();

        if ($size === null) {
            throw new RuntimeException('A public byte response needs a known body length.');
        }

        return $this->responseBuilder->withHeader($response, 'Content-Length', (string) $size);
    }
}
