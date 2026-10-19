<?php

declare(strict_types=1);

namespace Gates\Application;

use JsonException;

use function fwrite;
use function sprintf;
use function str_replace;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #3 — require-checker for the root and the framework manifests.
 *
 * AIC's own checker, ported from old AIC's gate:16 (AIC #43). The upstream
 * `maglnet/composer-require-checker` needs a `vendor/` beside each manifest;
 * `framework/` is path-installed and resolves through the root `vendor/`, so
 * this gate reads the root's `installed.json` instead.
 *
 * This gate walks each manifest in isolation:
 *   1. Build a many-to-many `psr-4-prefix → list<composer-package>` map
 *      from `vendor/composer/installed.json`.
 *   2. For each `composer.json` in `[root, framework/composer.json]`:
 *        a. Decode the manifest. Read `require:` (drop `require-dev`).
 *        b. Build the manifest's declared-prefix set + own-prefix set.
 *        c. Walk autoload paths recursively. If zero `*.php` files exist
 *           the manifest defers (legitimate during early bootstrap).
 *        d. Tokenise each file and emit every top-level `use` FQN(s).
 *        e. Classify each FQN against the always-OK list, the project
 *           allowlist, the manifest's own prefixes and finally the
 *           declared-prefix set.
 *   3. Print a `passed/failed/deferred` summary; exit 1 iff failed > 0
 *      (source-fix only; no baselines).
 */
final readonly class RequireCheckerGate
{
    /**
     * @param InstalledPackageMap     $installedPackageMap     Builds the prefix → owners map.
     * @param RequireCheckerAllowlist $requireCheckerAllowlist Loads the always-OK + project allowlist.
     * @param ManifestVerifier        $manifestVerifier        Verifies one manifest end-to-end.
     * @param ProjectManifests        $projectManifests        The manifests the gate checks.
     */
    public function __construct(
        private InstalledPackageMap $installedPackageMap = new InstalledPackageMap(),
        private RequireCheckerAllowlist $requireCheckerAllowlist = new RequireCheckerAllowlist(),
        private ManifestVerifier $manifestVerifier = new ManifestVerifier(),
        private ProjectManifests $projectManifests = new ProjectManifests(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success / progress messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     *
     * @throws JsonException When any inspected JSON file is malformed.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $prefixToPackages = $this->installedPackageMap->build($root, $stderr);

        if ($prefixToPackages === null) {
            return 1;
        }

        $allowlistedFqns = $this->requireCheckerAllowlist->load($root);
        $manifests = $this->projectManifests->paths($root);
        $tally = $this->verifyAllManifests($manifests, $root, $prefixToPackages, $allowlistedFqns, $stdout, $stderr);

        fwrite($stdout, sprintf(
            'gate:3 — summary: %d passed, %d failed, %d deferred.%s',
            $tally['passed'],
            $tally['failed'],
            $tally['deferred'],
            PHP_EOL,
        ));

        return $tally['failed'] > 0
            ? 1
            : 0;
    }

    /**
     * Verify every manifest and tally the outcome counts.
     *
     * @param list<string>                $manifests        Absolute paths to every manifest.
     * @param string                      $root             Repo-root absolute path.
     * @param array<string, list<string>> $prefixToPackages Prefix → owners map.
     * @param list<string>                $allowlistedFqns  Allowlist of always-OK FQNs.
     * @param resource                    $stdout           Stream progress messages are written to.
     * @param resource                    $stderr           Stream failure messages are written to.
     *
     * @throws JsonException When a manifest is malformed.
     *
     * @return array{passed: int, failed: int, deferred: int}
     */
    private function verifyAllManifests(
        array $manifests,
        string $root,
        array $prefixToPackages,
        array $allowlistedFqns,
        mixed $stdout,
        mixed $stderr,
    ): array {
        $tally = ['passed' => 0, 'failed' => 0, 'deferred' => 0];

        foreach ($manifests as $manifest) {
            $relManifest = str_replace($root . '/', '', $manifest);
            $status = $this->manifestVerifier->verify(
                $manifest,
                $relManifest,
                $root,
                $prefixToPackages,
                $allowlistedFqns,
                $stdout,
                $stderr,
            );
            $tally = $this->bumpTally($tally, $status);
        }

        return $tally;
    }

    /**
     * Increment the appropriate counter based on the verifier's verdict.
     *
     * @param array{passed: int, failed: int, deferred: int} $tally  Running tally.
     * @param string                                         $status One of `'pass'`, `'fail'`, `'defer'`.
     *
     * @return array{passed: int, failed: int, deferred: int}
     */
    private function bumpTally(array $tally, string $status): array
    {
        $key = match ($status) {
            'pass' => 'passed',
            'defer' => 'deferred',
            default => 'failed',
        };

        $tally[$key] += 1;

        return $tally;
    }
}
