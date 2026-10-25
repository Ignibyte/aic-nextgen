<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Domain\AssetConditions;
use AIC\Http\Domain\AssetFileInfo;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function min;
use function strlen;
use function time;

/** Public asset preconditions, ranges and cache metadata over one verified byte snapshot. */
final readonly class AssetResponses
{
    /**
     * @param ResponseBuilder $responseBuilder Makes responses and sets their headers.
     * @param FileResponses   $fileResponses   Preserves the shared file/range behavior.
     */
    public function __construct(private ResponseBuilder $responseBuilder, private FileResponses $fileResponses)
    {
    }

    /**
     * @param PublicAsset            $publicAsset   Its compiled media metadata.
     * @param AssetFileInfo          $assetFileInfo The immutable verified snapshot.
     * @param ServerRequestInterface $serverRequest GET or HEAD, with an optional Range.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface The public representation or its range, without a body for HEAD.
     */
    public function file(
        PublicAsset $publicAsset,
        AssetFileInfo $assetFileInfo,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        $now = time();
        $modified = min($assetFileInfo->modified, $now);
        $etag = '"' . $publicAsset->sha256 . '"';
        $assetConditions = new AssetConditions();
        $status = $assetConditions->status($serverRequest, $etag, $modified, $now);
        $response = $this->representation($publicAsset, $assetFileInfo, $serverRequest, $assetConditions, $status, $etag);

        return (new AssetResponseHeaders($this->responseBuilder))->apply(
            $response,
            $publicAsset,
            $modified,
            $now,
            $serverRequest->getUri()->getPath() === $publicAsset->path,
        );
    }

    /**
     * @param bool $head Whether a HEAD request needs the same failure metadata without its body.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface A session-free, uncached 404.
     */
    public function missing(bool $head): ResponseInterface
    {
        $message = 'Not Found';
        $response = $this->responseBuilder->create(404, 'text/plain; charset=utf-8', $head ? '' : $message);

        return $this->uncached($this->responseBuilder->withHeader($response, 'Content-Length', (string) strlen($message)));
    }

    /**
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface A session-free, uncached 405 with the supported methods.
     */
    public function unsupported(): ResponseInterface
    {
        $response = $this->responseBuilder->create(405, 'text/plain; charset=utf-8', 'Method Not Allowed');

        return $this->uncached($this->responseBuilder->withHeader($response, 'Allow', 'GET, HEAD'));
    }

    /**
     * @param PublicAsset            $publicAsset     Its compiled media metadata.
     * @param AssetFileInfo          $assetFileInfo   The verified bytes and same-handle metadata.
     * @param ServerRequestInterface $serverRequest   The selected GET/HEAD request.
     * @param AssetConditions        $assetConditions Applies If-Range only after preconditions.
     * @param int                    $status          The precondition result.
     * @param string                 $etag            The verified strong entity tag.
     *
     * @throws InvalidArgumentException When response metadata is invalid.
     * @throws RuntimeException         When a response body cannot be created.
     *
     * @return ResponseInterface Its bodyless conditional/HEAD response or selected byte representation.
     */
    private function representation(
        PublicAsset $publicAsset,
        AssetFileInfo $assetFileInfo,
        ServerRequestInterface $serverRequest,
        AssetConditions $assetConditions,
        int $status,
        string $etag,
    ): ResponseInterface {
        if ($status !== 200) {
            return $this->responseBuilder->create($status, $publicAsset->mediaType, '');
        }

        if ($serverRequest->getMethod() === 'HEAD') {
            $response = $this->responseBuilder->create(200, $publicAsset->mediaType, '');

            return $this->responseBuilder->withHeader($response, 'Content-Length', (string) $assetFileInfo->size());
        }

        return $this->fileResponses->bytes(
            $assetFileInfo->contents,
            $publicAsset->mediaType,
            $assetConditions->range($serverRequest, $etag),
        );
    }

    /**
     * @param ResponseInterface $response A public asset response, including failures and ranges.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface Its conservative cache and declared media handling.
     */
    private function uncached(ResponseInterface $response): ResponseInterface
    {
        $uncached = $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-store');

        return $this->responseBuilder->withHeader($uncached, 'X-Content-Type-Options', 'nosniff');
    }
}
