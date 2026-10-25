<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use Psr\Http\Message\ServerRequestInterface;

use function trim;

/** Evaluate verified GET/HEAD representation preconditions before considering any byte range. */
final readonly class AssetConditions
{
    /**
     * @param EntityTags $entityTags Compares strong and weak tag lists.
     * @param HttpDate   $httpDate   Validates HTTP dates without accepting relative date expressions.
     */
    public function __construct(
        private EntityTags $entityTags = new EntityTags(),
        private HttpDate $httpDate = new HttpDate(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The selected GET/HEAD request.
     * @param string                 $etag          The current strong quoted entity tag.
     * @param int                    $modified      Its modification time, clamped to the response's Date.
     * @param int                    $now           The one response origination time.
     *
     * @return int The precondition outcome: 200, 304 or 412.
     */
    public function status(ServerRequestInterface $serverRequest, string $etag, int $modified, int $now): int
    {
        if (!$this->unchanged($serverRequest, $etag, $modified, $now)) {
            return 412;
        }

        return $this->notModified($serverRequest, $etag, $modified, $now)
            ? 304
            : 200;
    }

    /**
     * @param ServerRequestInterface $serverRequest The selected request after successful preconditions.
     * @param string                 $etag          The current strong quoted entity tag.
     *
     * @return string Its Range, or empty for HEAD or a nonmatching, weak, malformed or date-form If-Range.
     */
    public function range(ServerRequestInterface $serverRequest, string $etag): string
    {
        if ($serverRequest->getMethod() === 'HEAD') {
            return '';
        }

        if ($serverRequest->hasHeader('If-Range') && trim($serverRequest->getHeaderLine('If-Range'), " \t") !== $etag) {
            return '';
        }

        return $serverRequest->getHeaderLine('Range');
    }

    /**
     * @param ServerRequestInterface $serverRequest The selected request after modification preconditions.
     * @param string                 $etag          The current strong tag.
     * @param int                    $modified      The effective modification time.
     * @param int                    $now           The response origination time.
     *
     * @return bool Whether If-None-Match or its lower-priority If-Modified-Since validates the stored representation.
     */
    private function notModified(ServerRequestInterface $serverRequest, string $etag, int $modified, int $now): bool
    {
        if ($serverRequest->hasHeader('If-None-Match')) {
            return $this->entityTags->matches($serverRequest->getHeaderLine('If-None-Match'), $etag, true);
        }

        $since = $this->httpDate->parse(trim($serverRequest->getHeaderLine('If-Modified-Since'), " \t"), $now);

        return $since !== null && $modified <= $since;
    }

    /**
     * @param ServerRequestInterface $serverRequest The selected request.
     * @param string                 $etag          The current strong entity tag.
     * @param int                    $modified      Its modification time.
     * @param int                    $now           The response origination time.
     *
     * @return bool Whether If-Match or its lower-priority If-Unmodified-Since permits processing.
     */
    private function unchanged(ServerRequestInterface $serverRequest, string $etag, int $modified, int $now): bool
    {
        if ($serverRequest->hasHeader('If-Match')) {
            return $this->entityTags->matches($serverRequest->getHeaderLine('If-Match'), $etag, false);
        }

        $since = $this->httpDate->parse(trim($serverRequest->getHeaderLine('If-Unmodified-Since'), " \t"), $now);

        return $since === null || $modified <= $since;
    }
}
