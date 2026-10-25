<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/** An explicitly named public asset whose owner-relative source and role the build validates. */
final readonly class ModuleAsset implements ModuleDeclaration
{
    /**
     * @param string          $name            The local identifier, such as styles or favicon.
     * @param string          $source          Its owner-relative assets/ path, including any declared subdirectories.
     * @param ModuleAssetKind $moduleAssetKind Whether it is CSS, JavaScript or another supported public file.
     */
    public function __construct(
        public string $name,
        public string $source,
        public ModuleAssetKind $moduleAssetKind,
    ) {
    }
}
