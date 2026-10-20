<?php

declare(strict_types=1);

namespace App\Site;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /download/{file}: a file from the app's resources directory, as a
 * download.
 */
final readonly class ResourceDownload implements Stream
{
    /**
     * @param RequestedFile $requestedFile The app's resources.
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
        return $this->requestedFile->download($serverRequest);
    }
}
