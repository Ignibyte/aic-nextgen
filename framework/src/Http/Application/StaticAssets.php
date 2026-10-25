<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Contracts\PublicAssets;
use AIC\Http\Domain\AssetFileInfo;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function in_array;
use function str_starts_with;

/** Reserve /assets before any module provider, route assembly, session or database work. */
final readonly class StaticAssets implements RequestHandlerInterface
{
    /**
     * @param PublicAssets            $publicAssets   The compiled exact lookup.
     * @param AssetFiles              $assetFiles     Verifies only the catalog's files.
     * @param AssetResponses          $assetResponses Writes the public responses.
     * @param RequestHandlerInterface $requestHandler The inert dynamic handler, invoked outside the reserved namespace.
     */
    public function __construct(
        private PublicAssets $publicAssets,
        private AssetFiles $assetFiles,
        private AssetResponses $assetResponses,
        private RequestHandlerInterface $requestHandler,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The incoming request.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface A catalog response or the dynamic application's response.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path !== '/assets' && !str_starts_with($path, '/assets/')) {
            return $this->requestHandler->handle($request);
        }

        return in_array($request->getMethod(), ['GET', 'HEAD'], true)
            ? $this->asset($request, $path)
            : $this->assetResponses->unsupported();
    }

    /**
     * @param ServerRequestInterface $serverRequest GET or HEAD within the reserved namespace.
     * @param string                 $path          Its exact undecoded path.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface Its exact public file, or the same uncached 404 for any unavailable representation.
     */
    private function asset(ServerRequestInterface $serverRequest, string $path): ResponseInterface
    {
        $publicAsset = $this->publicAssets->byPath($path);

        if (!$publicAsset instanceof PublicAsset) {
            return $this->assetResponses->missing($serverRequest->getMethod() === 'HEAD');
        }

        $assetFileInfo = $this->assetFiles->verified($publicAsset);

        return $assetFileInfo instanceof AssetFileInfo
            ? $this->assetResponses->file($publicAsset, $assetFileInfo, $serverRequest)
            : $this->assetResponses->missing($serverRequest->getMethod() === 'HEAD');
    }
}
