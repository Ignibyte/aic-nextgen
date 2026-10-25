<?php

declare(strict_types=1);

namespace Gates\Domain;

/** One validated declaration, resolved from its module to trusted project and publication paths. */
final readonly class AssetInput
{
    /**
     * @param string    $name      The theme-qualified logical identifier.
     * @param string    $source    The source relative to the project folder.
     * @param string    $file      The output relative to public/assets/.
     * @param AssetKind $assetKind Its role, from its extension.
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $file,
        public AssetKind $assetKind,
    ) {
    }
}
