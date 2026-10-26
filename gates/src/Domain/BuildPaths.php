<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Where the build writes (#167): everything under `build/`, beside `build/surface.json` and `build/assets.json`, so
 * the project's top level shows only what people edit. `public/` keeps only the entry, `index.php`.
 */
final readonly class BuildPaths
{
    /**
     * The generated wiring, lookups and escaped templates (`App\Generated\`), relative to the project.
     */
    public const string GENERATED = 'build/generated';

    /**
     * The published assets AIC's asset handler serves at `/assets/`, relative to the project.
     */
    public const string ASSETS = 'build/assets';
}
