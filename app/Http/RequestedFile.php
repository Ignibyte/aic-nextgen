<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Domain\FileDownload;
use Psr\Http\Message\ServerRequestInterface;

use function basename;
use function is_string;
use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * The file a request's `file` attribute names, in one directory: only its
 * base name is used, so a request cannot reach outside the directory. Its
 * media type comes from its extension, and one byte range is honoured.
 */
final readonly class RequestedFile
{
    /**
     * @param string $directory The directory the files are served from.
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute.
     *
     * @return FileDownload The file as a download under its name.
     */
    public function download(ServerRequestInterface $serverRequest): FileDownload
    {
        $name = self::name($serverRequest);

        return new FileDownload($this->directory . '/' . $name, self::mediaType($name), $name, $serverRequest->getHeaderLine('Range'));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute.
     *
     * @return FileDownload The file, shown in place.
     */
    public function inline(ServerRequestInterface $serverRequest): FileDownload
    {
        $name = self::name($serverRequest);

        return new FileDownload($this->directory . '/' . $name, self::mediaType($name), '', $serverRequest->getHeaderLine('Range'));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute.
     *
     * @return string The file's base name.
     */
    private static function name(ServerRequestInterface $serverRequest): string
    {
        $file = $serverRequest->getAttribute('file');

        return basename(is_string($file) ? $file : '');
    }

    /**
     * @param string $name A file name.
     *
     * @return string Its media type, by extension.
     */
    private static function mediaType(string $name): string
    {
        return match (pathinfo($name, PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'text/javascript; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }
}
