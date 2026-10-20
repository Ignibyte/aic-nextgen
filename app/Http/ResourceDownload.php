<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\FileDownload;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function basename;
use function is_string;

/**
 * GET /download/{file}: a file from the app's resources directory, as a
 * download. Only the base name of the requested file is used, so a request
 * cannot reach outside the directory.
 */
final readonly class ResourceDownload implements Stream
{
    /**
     * @param string $resources The app's resources directory.
     */
    public function __construct(
        private string $resources,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute and an optional `Range` header.
     *
     * @return StreamResponse The file.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $file = $serverRequest->getAttribute('file');
        $name = basename(is_string($file) ? $file : '');

        return new FileDownload($this->resources . '/' . $name, 'text/plain; charset=utf-8', $name, $serverRequest->getHeaderLine('Range'));
    }
}
