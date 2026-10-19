<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_any;
use function in_array;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Decides whether a used FQN is covered by the manifest's declared
 * dependencies, the always-OK list or the manifest's own prefixes.
 *
 * Single-axis ordering applied in order:
 *   1. Unqualified FQNs (no `\`) — only allowlisted symbols pass.
 *   2. Allowlist hit — an exact FQN or a trailing-`\` namespace prefix entry.
 *   3. Manifest's own PSR-4 prefix hit (intra-package use).
 *   4. Declared-prefix hit. The app's `AIC\…` uses pass here, through its
 *      `aic/framework` require.
 */
final readonly class FqnCoverageChecker
{
    /**
     * Decide whether a used FQN is covered.
     *
     * @param string       $fqn              Used FQN, e.g. `Psr\Log\LoggerInterface`.
     * @param list<string> $allowlistedFqns  Hardcoded ALWAYS_OK + project allowlist.
     * @param list<string> $ownPrefixes      The manifest's own PSR-4 prefixes.
     * @param list<string> $declaredPrefixes Prefixes owned by any declared require: package.
     */
    public function isCovered(
        string $fqn,
        array $allowlistedFqns,
        array $ownPrefixes,
        array $declaredPrefixes,
    ): bool {
        if (!str_contains($fqn, '\\')) {
            return in_array($fqn, $allowlistedFqns, true);
        }

        return $this->isQualifiedCovered($fqn, $allowlistedFqns, $ownPrefixes, $declaredPrefixes);
    }

    /**
     * Decide whether a namespaced FQN is covered. Splits `isCovered` to keep
     * cognitive complexity within the per-method ceiling.
     *
     * @param string       $fqn              Used FQN.
     * @param list<string> $allowlistedFqns  Hardcoded ALWAYS_OK + project allowlist.
     * @param list<string> $ownPrefixes      The manifest's own PSR-4 prefixes.
     * @param list<string> $declaredPrefixes Prefixes owned by any declared require: package.
     */
    private function isQualifiedCovered(
        string $fqn,
        array $allowlistedFqns,
        array $ownPrefixes,
        array $declaredPrefixes,
    ): bool {
        if ($this->matchesAllowlist($fqn, $allowlistedFqns)) {
            return true;
        }

        if ($this->matchesAnyPrefix($fqn, $ownPrefixes)) {
            return true;
        }

        return $this->matchesAnyPrefix($fqn, $declaredPrefixes);
    }

    /**
     * Decide whether one of `$prefixes` is a prefix of `$fqn`.
     *
     * @param string       $fqn      Used FQN.
     * @param list<string> $prefixes PSR-4 prefixes to test.
     */
    private function matchesAnyPrefix(string $fqn, array $prefixes): bool
    {
        return array_any(
            $prefixes,
            static fn (string $prefix): bool => str_starts_with($fqn, $prefix),
        );
    }

    /**
     * Decide whether `$fqn` is covered by the allowlist — either matched
     * verbatim (an exact FQN entry) or falling under a namespace-prefix
     * entry (one written with a trailing `\`). Folds the two allowlist
     * forms into one predicate so `isQualifiedCovered` keeps a single
     * allowlist branch within the per-method cognitive-complexity ceiling.
     *
     * @param string       $fqn             Used FQN.
     * @param list<string> $allowlistedFqns Allowlist entries (exact + prefix).
     */
    private function matchesAllowlist(string $fqn, array $allowlistedFqns): bool
    {
        return in_array($fqn, $allowlistedFqns, true)
            || $this->matchesAllowlistPrefix($fqn, $allowlistedFqns);
    }

    /**
     * Decide whether `$fqn` falls under an allowlist entry written as a
     * namespace prefix — an entry ending in `\` (e.g. `PhpParser\`). Exact
     * entries (no trailing `\`) are ignored here; they are matched verbatim
     * by the `in_array` check in `matchesAllowlist`. This is how a
     * build-time-only vendor namespace (the render-fn emitter's
     * `nikic/php-parser` AST symbols, kept in `require-dev` and thus dropped
     * by the checker) is covered with a single entry.
     *
     * @param string       $fqn             Used FQN.
     * @param list<string> $allowlistedFqns Allowlist entries (exact + prefix).
     */
    private function matchesAllowlistPrefix(string $fqn, array $allowlistedFqns): bool
    {
        return array_any(
            $allowlistedFqns,
            static fn (string $entry): bool => str_ends_with($entry, '\\') && str_starts_with($fqn, $entry),
        );
    }
}
