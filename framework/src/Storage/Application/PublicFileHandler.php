<?php

declare(strict_types=1);

namespace AIC\Storage\Application;

use AIC\Http\Application\AssetResponses;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\AssetMediaTypes;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function basename;
use function in_array;
use function str_starts_with;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Serves the app's public files at `/files/<key>` (#213), after the assets and
 * before the dynamic application, so a public file costs no session, routing
 * or database work. A file is validated and ranged as a download is, cached as
 * `public, no-cache` so a replaced file shows, and sent with `nosniff` and a
 * `sandbox` policy. Only images, PDF and plain text are shown inline; any
 * other file, an SVG included, is an attachment, so stored content never runs
 * as the site.
 */
final readonly class PublicFileHandler implements RequestHandlerInterface
{
    /**
     * The media types shown inline, by extension.
     */
    private const array INLINE = [
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'txt' => 'text/plain; charset=utf-8',
        'webp' => 'image/webp',
    ];

    /**
     * @param PublicFiles             $publicFiles     The files.
     * @param FileResponses           $fileResponses   Writes a validated, ranged file.
     * @param AssetResponses          $assetResponses  Writes the uncached 404 and 405.
     * @param ResponseBuilder         $responseBuilder Sets the headers.
     * @param RequestHandlerInterface $requestHandler  The dynamic application, for every other path.
     * @param AssetMediaTypes         $assetMediaTypes The media type of a file sent as an attachment.
     */
    public function __construct(
        private PublicFiles $publicFiles,
        private FileResponses $fileResponses,
        private AssetResponses $assetResponses,
        private ResponseBuilder $responseBuilder,
        private RequestHandlerInterface $requestHandler,
        private AssetMediaTypes $assetMediaTypes = new AssetMediaTypes(),
    ) {
    }

    /**
     * @param ServerRequestInterface $request The incoming request.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface The public file, a 404 or a 405 under `/files/`; the application's answer elsewhere.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path !== '/files' && !str_starts_with($path, PublicFiles::PREFIX)) {
            return $this->requestHandler->handle($request);
        }

        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $this->assetResponses->unsupported();
        }

        $file = $this->publicFiles->served(substr($path, 7));

        return $file === null
            ? $this->assetResponses->missing($request->getMethod() === 'HEAD')
            : $this->file($file, $request);
    }

    /**
     * @param string                 $file          The file's path.
     * @param ServerRequestInterface $serverRequest The request: its conditions and `Range`.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface The file, inline or as an attachment, with the public file headers.
     */
    private function file(string $file, ServerRequestInterface $serverRequest): ResponseInterface
    {
        $name = basename($file);
        $dot = strrpos($name, '.');
        $inline = self::INLINE[$dot === false ? '' : strtolower(substr($name, $dot + 1))] ?? '';
        $response = $inline === ''
            ? $this->fileResponses->download($file, $this->assetMediaTypes->forFile($name), $name, $serverRequest)
            : $this->fileResponses->download($file, $inline, '', $serverRequest);
        $cached = $this->responseBuilder->withHeader($response, 'Cache-Control', 'public, no-cache');
        $sniffless = $this->responseBuilder->withHeader($cached, 'X-Content-Type-Options', 'nosniff');

        return $this->responseBuilder->withHeader($sniffless, 'Content-Security-Policy', 'sandbox');
    }
}
