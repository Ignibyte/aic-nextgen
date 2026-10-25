<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

/** Shared extension media types for declared public files and ordinary downloads. */
final readonly class AssetMediaTypes
{
    /**
     * @param string $file A file name or trusted path.
     *
     * @return string Its media type, or the ordinary-download binary fallback.
     */
    public function forFile(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'js', 'mjs' => 'text/javascript; charset=utf-8',
            'json', 'map' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'ico' => 'image/vnd.microsoft.icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }
}
