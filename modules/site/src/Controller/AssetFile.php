<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Site\Service\RequestedFile;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /assets/{file}: a built asset from public/assets, inline. DDEV's nginx
 * and a production proxy serve these files themselves; this route serves
 * them for the compiled site, which serves no static files (#39).
 */
final readonly class AssetFile implements Stream
{
    /**
     * The built assets, relative to the app's root.
     */
    private const string DIRECTORY = 'public/assets';

    /**
     * @param RequestedFile $requestedFile Files under the app's root.
     */
    public function __construct(
        private RequestedFile $requestedFile,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute.
     *
     * @return StreamResponse The file inline, with its media type.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->requestedFile->inline($serverRequest, self::DIRECTORY);
    }
}
