<?php

declare(strict_types=1);

namespace AIC\Http\Contracts;

/** The build's complete public catalog, addressed by logical name or exact canonical or compatibility URL. */
interface PublicAssets
{
    /**
     * @param string $name The declared owner/path identifier.
     *
     * @return PublicAsset|null Its canonical fingerprinted representation, or null when undeclared.
     */
    public function named(string $name): ?PublicAsset;

    /**
     * @param string $path The exact canonical or compatibility request path, without its query string.
     *
     * @return PublicAsset|null Its compiled representation, or null when undeclared.
     */
    public function byPath(string $path): ?PublicAsset;
}
