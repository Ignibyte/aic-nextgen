<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Site\Service\RequestedFile;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /download/{file}: a file from the site module's resources directory,
 * as a download.
 */
final readonly class ResourceDownload implements Stream
{
    /**
     * The downloadable files, relative to the app's root.
     */
    private const string DIRECTORY = 'modules/site/resources';

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
     * @return StreamResponse The file as a download, or one byte range of it.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->requestedFile->download($serverRequest, self::DIRECTORY);
    }
}
