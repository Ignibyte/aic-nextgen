<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Per-manifest verification context built by `ManifestVerifier`.
 *
 * Bundles the three pieces of state the per-file scan reads:
 *   - `declaredPrefixes` — every PSR-4 prefix owned by some declared
 *     `require:` package.
 *   - `ownPrefixes`      — the manifest's own `autoload.psr-4` keys
 *     (intra-package use is exempt from cross-check).
 *   - `sourceFiles`      — every `*.php` file under the manifest's
 *     PSR-4 paths.
 *
 * @api
 */
final readonly class ManifestContext
{
    /**
     * @param list<string> $declaredPrefixes PSR-4 prefixes owned by declared require: packages.
     * @param list<string> $ownPrefixes      The manifest's own PSR-4 prefixes.
     * @param list<string> $sourceFiles      Absolute paths of `.php` files under the manifest.
     */
    public function __construct(
        public array $declaredPrefixes,
        public array $ownPrefixes,
        public array $sourceFiles,
    ) {
    }
}
