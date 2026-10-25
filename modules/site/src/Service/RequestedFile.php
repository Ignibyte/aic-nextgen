<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Http\Domain\AssetMediaTypes;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function basename;
use function is_string;

/**
 * The file a request's `file` attribute names, in a directory under the app's
 * root the caller names: only its base name is used, so a request cannot
 * reach outside the directory. Its
 * media type comes from its extension, and one byte range is honoured.
 */
final readonly class RequestedFile
{
    /**
     * @param string          $root            The app's root directory.
     * @param AssetMediaTypes $assetMediaTypes The framework's extension-to-media-type mapping.
     */
    public function __construct(
        private string $root,
        private AssetMediaTypes $assetMediaTypes = new AssetMediaTypes(),
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

        return new FileDownload($path, $this->assetMediaTypes->forFile($name), $name, $serverRequest->getHeaderLine('Range'));
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
}
