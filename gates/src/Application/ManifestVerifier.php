<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ManifestContext;
use JsonException;

use function array_any;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function count;
use function dirname;
use function file_get_contents;
use function fwrite;
use function json_decode;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

/**
 * Verifies one composer manifest against the installed-package prefix
 * map: every used FQN must be backed by either the always-OK list, the
 * project allowlist, the manifest's own prefixes, or a declared-require's
 * PSR-4 prefix.
 *
 * Returns `'pass'`, `'fail'`, or `'defer'` (legitimate during early
 * bootstrap of a new manifest with no source files yet).
 */
final readonly class ManifestVerifier
{
    /**
     * @param PhpSourceCollector    $phpSourceCollector    Walks manifest psr-4 paths to collect `*.php`.
     * @param UseStatementExtractor $useStatementExtractor Tokenizer-based extractor for top-level `use`.
     * @param FqnCoverageChecker    $fqnCoverageChecker    Decides whether a used FQN is covered.
     */
    public function __construct(
        private PhpSourceCollector $phpSourceCollector = new PhpSourceCollector(),
        private UseStatementExtractor $useStatementExtractor = new UseStatementExtractor(),
        private FqnCoverageChecker $fqnCoverageChecker = new FqnCoverageChecker(),
    ) {
    }

    /**
     * Verify one manifest.
     *
     * @param string                      $manifest         Absolute path to a `composer.json`.
     * @param string                      $relManifest      Path relative to repo root (for diagnostics).
     * @param string                      $root             Repo-root absolute path.
     * @param array<string, list<string>> $prefixToPackages Prefix → owners map from `installed.json`.
     * @param list<string>                $allowlistedFqns  Hardcoded ALWAYS_OK + project allowlist.
     * @param resource                    $stdout           Stream success / progress messages are written to.
     * @param resource                    $stderr           Stream failure messages are written to.
     *
     * @throws JsonException When the manifest is not valid JSON.
     */
    public function verify(
        string $manifest,
        string $relManifest,
        string $root,
        array $prefixToPackages,
        array $allowlistedFqns,
        mixed $stdout,
        mixed $stderr,
    ): string {
        $manifestContext = $this->loadContext($manifest, $relManifest, $prefixToPackages, $stderr);

        if (!$manifestContext instanceof ManifestContext) {
            return 'fail';
        }

        return $this->dispatchVerdict($manifestContext, $relManifest, $root, $allowlistedFqns, $stdout, $stderr);
    }

    /**
     * Decide pass/fail/defer based on `$context` and emit the per-manifest summary line.
     *
     * @param ManifestContext $manifestContext Per-manifest verification context.
     * @param string          $relManifest     Path relative to repo root (for diagnostics).
     * @param string          $root            Repo-root absolute path.
     * @param list<string>    $allowlistedFqns Allowlist of always-OK FQNs.
     * @param resource        $stdout          Stream success / progress messages are written to.
     * @param resource        $stderr          Stream failure messages are written to.
     */
    private function dispatchVerdict(
        ManifestContext $manifestContext,
        string $relManifest,
        string $root,
        array $allowlistedFqns,
        mixed $stdout,
        mixed $stderr,
    ): string {
        if ($manifestContext->sourceFiles === []) {
            return $this->reportDeferred($relManifest, $stdout);
        }

        $violations = $this->scanFilesForViolations($manifestContext, $root, $allowlistedFqns);

        if ($violations === []) {
            fwrite($stdout, sprintf('gate:3 — %s clean.%s', $relManifest, PHP_EOL));

            return 'pass';
        }

        $this->reportViolations($relManifest, $violations, $stderr);

        return 'fail';
    }

    /**
     * Emit the per-manifest defer line and return the verdict.
     *
     * @param string   $relManifest Path relative to repo root.
     * @param resource $stdout      Stream success / progress messages are written to.
     */
    private function reportDeferred(string $relManifest, mixed $stdout): string
    {
        fwrite($stdout, sprintf(
            'gate:3 — %s has no source files yet; deferring this manifest.%s',
            $relManifest,
            PHP_EOL,
        ));

        return 'defer';
    }

    /**
     * Load + decode `$manifest` and build the verification context. Returns
     * `null` only when the manifest cannot be read.
     *
     * @param string                      $manifest         Absolute path to a `composer.json`.
     * @param string                      $relManifest      Path relative to repo root (for diagnostics).
     * @param array<string, list<string>> $prefixToPackages Prefix → owners map from `installed.json`.
     * @param resource                    $stderr           Stream failure messages are written to.
     *
     * @throws JsonException When the manifest is not valid JSON.
     */
    private function loadContext(
        string $manifest,
        string $relManifest,
        array $prefixToPackages,
        mixed $stderr,
    ): ?ManifestContext {
        $raw = file_get_contents($manifest);

        if ($raw === false) {
            fwrite($stderr, sprintf('gate:3 — cannot read %s.%s', $relManifest, PHP_EOL));

            return null;
        }

        /** @var array{require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}} $composer */
        $composer = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $require = $composer['require'] ?? [];
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        return new ManifestContext(
            declaredPrefixes: $this->collectDeclaredPrefixes(array_keys($require), $prefixToPackages),
            ownPrefixes: array_keys($psr4),
            sourceFiles: $this->phpSourceCollector->collect(dirname($manifest), $psr4),
        );
    }

    /**
     * Build the union of PSR-4 prefixes owned by any package the manifest declares.
     *
     * @param list<string>                $declaredPackages Package names from the require: block.
     * @param array<string, list<string>> $prefixToPackages Prefix → owners map.
     *
     * @return list<string>
     */
    private function collectDeclaredPrefixes(array $declaredPackages, array $prefixToPackages): array
    {
        $declaredSet = array_flip($declaredPackages);
        $prefixes = [];

        foreach ($prefixToPackages as $prefix => $owners) {
            if (!$this->declaredOwnsPrefix($owners, $declaredSet)) {
                continue;
            }

            $prefixes[] = $prefix;
        }

        return $prefixes;
    }

    /**
     * Decide whether any `$owners` entry is in `$declaredSet`.
     *
     * @param list<string>       $owners      Packages that own a single prefix.
     * @param array<string, int> $declaredSet Lookup map of the manifest's declared packages.
     */
    private function declaredOwnsPrefix(array $owners, array $declaredSet): bool
    {
        return array_any($owners, static fn (string $owner): bool => array_key_exists($owner, $declaredSet));
    }

    /**
     * Scan every source file in `$context` for unbacked imports.
     *
     * @param ManifestContext $manifestContext Per-manifest context.
     * @param string          $root            Repo-root absolute path.
     * @param list<string>    $allowlistedFqns Allowlist of always-OK FQNs.
     *
     * @return list<string>
     */
    private function scanFilesForViolations(
        ManifestContext $manifestContext,
        string $root,
        array $allowlistedFqns,
    ): array {
        $violations = [];

        foreach ($manifestContext->sourceFiles as $sourceFile) {
            $violations = [
                ...$violations,
                ...$this->scanOneFile($sourceFile, $manifestContext, $root, $allowlistedFqns),
            ];
        }

        return $violations;
    }

    /**
     * Scan one source file for unbacked imports.
     *
     * @param string          $sourceFile      Absolute path to the source file.
     * @param ManifestContext $manifestContext Per-manifest context.
     * @param string          $root            Repo-root absolute path.
     * @param list<string>    $allowlistedFqns Allowlist of always-OK FQNs.
     *
     * @return list<string>
     */
    private function scanOneFile(
        string $sourceFile,
        ManifestContext $manifestContext,
        string $root,
        array $allowlistedFqns,
    ): array {
        $contents = file_get_contents($sourceFile);

        if ($contents === false) {
            return [sprintf('%s: cannot read source file', str_replace($root . '/', '', $sourceFile))];
        }

        $violations = [];

        foreach ($this->useStatementExtractor->extract($contents) as $fqn) {
            $violation = $this->checkFqn($sourceFile, $fqn, $manifestContext, $root, $allowlistedFqns);

            if ($violation === null) {
                continue;
            }

            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * Check one FQN against the coverage rules; return a violation message or `null`.
     *
     * @param string          $sourceFile      Absolute path to the source file.
     * @param string          $fqn             Used FQN.
     * @param ManifestContext $manifestContext Per-manifest context.
     * @param string          $root            Repo-root absolute path.
     * @param list<string>    $allowlistedFqns Allowlist of always-OK FQNs.
     */
    private function checkFqn(
        string $sourceFile,
        string $fqn,
        ManifestContext $manifestContext,
        string $root,
        array $allowlistedFqns,
    ): ?string {
        $covered = $this->fqnCoverageChecker->isCovered(
            $fqn,
            $allowlistedFqns,
            $manifestContext->ownPrefixes,
            $manifestContext->declaredPrefixes,
        );

        if ($covered) {
            return null;
        }

        return sprintf(
            '%s: uses %s — no require: declares the package owning this namespace',
            str_replace($root . '/', '', $sourceFile),
            $fqn,
        );
    }

    /**
     * Emit the per-manifest violation block to `$stderr`.
     *
     * @param string       $relManifest Path relative to repo root.
     * @param list<string> $violations  Per-FQN violation messages.
     * @param resource     $stderr      Stream to write to.
     */
    private function reportViolations(string $relManifest, array $violations, mixed $stderr): void
    {
        fwrite($stderr, sprintf(
            'gate:3 — %s reported %d unbacked symbol(s):%s',
            $relManifest,
            count($violations),
            PHP_EOL,
        ));

        foreach ($violations as $violation) {
            fwrite($stderr, sprintf('  - %s%s', $violation, PHP_EOL));
        }
    }
}
