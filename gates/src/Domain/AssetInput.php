<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Module\Contracts\ModuleAssetKind;

/** One validated declaration, resolved from its module to trusted project and publication paths. */
final readonly class AssetInput
{
    /**
     * @param string          $name            The owner-qualified logical identifier.
     * @param string          $source          The source relative to the project folder.
     * @param string          $file            The output relative to public/assets/.
     * @param ModuleAssetKind $moduleAssetKind The declared publication role.
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $file,
        public ModuleAssetKind $moduleAssetKind,
    ) {
    }
}
