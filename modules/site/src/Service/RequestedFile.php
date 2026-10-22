<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Controller\Domain\FileDownload;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function basename;
use function is_string;
use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * The file a request's `file` attribute names, in a directory under the app's
 * root the caller names: only its base name is used, so a request cannot
 * reach outside the directory. Its
 * media type comes from its extension, and one byte range is honoured.
 */
final readonly class RequestedFile
{
    /**
     * @param string $root The app's root directory.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute.
     * @param string                 $directory     The directory it is served from, relative to the app's root.
     *
     * @return FileDownload The file as a download under its name.
     */
    public function download(ServerRequestInterface $serverRequest, string $directory): FileDownload
    {
        $name = self::name($serverRequest, 'file');

        $path = $this->root . '/' . $directory . '/' . $name;

        return new FileDownload($path, self::mediaType($name), $name, $serverRequest->getHeaderLine('Range'));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `file` attribute and an optional `dir`.
     * @param string                 $directory     The directory it is served from, relative to the app's root.
     *
     * @return FileDownload The file, shown in place.
     */
    public function inline(ServerRequestInterface $serverRequest, string $directory): FileDownload
    {
        $name = self::name($serverRequest, 'file');
        $subdirectory = self::name($serverRequest, 'dir');
        $base = $this->root . '/' . $directory;
        $path = $subdirectory === ''
            ? $base . '/' . $name
            : $base . '/' . $subdirectory . '/' . $name;

        return new FileDownload($path, self::mediaType($name), '', $serverRequest->getHeaderLine('Range'));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the route's attributes.
     * @param string                 $attribute     `file`, or `dir` for an asset in a directory (Tessera's release, #70).
     *
     * @return string The attribute's base name, so it never leaves the directory; empty when absent.
     */
    private static function name(ServerRequestInterface $serverRequest, string $attribute): string
    {
        $attributes = $serverRequest->getAttributes();

        return basename(array_key_exists($attribute, $attributes) && is_string($attributes[$attribute]) ? $attributes[$attribute] : '');
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
            'map' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }
}
