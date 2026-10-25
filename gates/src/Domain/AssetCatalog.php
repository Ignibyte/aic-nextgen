<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Http\Contracts\PublicAsset;

/** The complete non-writing asset plan, including the private inventory and every public output. */
final readonly class AssetCatalog
{
    /**
     * @param list<PublicAsset>     $assets  The representations and their trusted metadata.
     * @param array<string, string> $outputs Output bytes by absolute path, including build/assets.json.
     */
    public function __construct(public array $assets, public array $outputs)
    {
    }
}
