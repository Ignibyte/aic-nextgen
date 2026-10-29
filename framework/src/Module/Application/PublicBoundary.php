<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Http\Application\AssetFiles;
use AIC\Http\Application\AssetResponses;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Application\StaticAssets;
use AIC\Http\Contracts\PublicAssets;
use AIC\Storage\Application\PublicFileHandler;
use AIC\Storage\Application\PublicFiles;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What answers a request before the modules are assembled (#213): the
 * compiled assets at `/assets/`, then the app's public files at `/files/`,
 * neither opening a session or the database. Every other request reaches the
 * dynamic application.
 */
final readonly class PublicBoundary
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param string                  $root           The app's root.
     * @param PublicAssets            $publicAssets   The compiled public catalog.
     * @param RequestHandlerInterface $requestHandler The modules' application.
     *
     * @return StaticAssets The assets, then the public files, then the dynamic application.
     */
    public function around(
        string $root,
        PublicAssets $publicAssets,
        RequestHandlerInterface $requestHandler,
    ): StaticAssets {
        $fileResponses = new FileResponses($this->responseBuilder);
        $assetResponses = new AssetResponses($this->responseBuilder, $fileResponses);
        $publicFileHandler = new PublicFileHandler(
            new PublicFiles($root),
            $fileResponses,
            $assetResponses,
            $this->responseBuilder,
            $requestHandler,
        );

        return new StaticAssets($publicAssets, new AssetFiles($root), $assetResponses, $publicFileHandler);
    }
}
