<?php

declare(strict_types=1);

namespace Gates\Application;

use ReflectionClass;

use function array_any;
use function class_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function mb_strtolower;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Decides whether a used FQN is covered by the manifest's declared
 * dependencies, the always-OK list or the manifest's own prefixes.
 *
 * Single-axis ordering applied in order:
 *   1. Unqualified FQNs (no `\`) — allowlisted symbols pass, and so does a
 *      built-in class whose PHP extension the manifest requires (`ext-pdo`
 *      backs `PDO`).
 *   2. Allowlist hit — an exact FQN or a trailing-`\` namespace prefix entry.
 *   3. Manifest's own PSR-4 prefix hit (intra-package use).
 *   4. Declared-prefix hit. The app's `AIC\…` uses pass here, through its
 *      `ignibyte/aic-framework` require.
 *   5. A namespaced internal class whose actual extension is required also passes.
 *      Extension types are already loaded, so coverage never invokes user autoload.
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
     * @param list<string> $declaredPackages Every package name in the manifest's require: block.
     */
    public function isCovered(
        string $fqn,
        array $allowlistedFqns,
        array $ownPrefixes,
        array $declaredPrefixes,
        array $declaredPackages,
    ): bool {
        if (!str_contains($fqn, '\\')) {
            return in_array($fqn, $allowlistedFqns, true) || self::extensionDeclared($fqn, $declaredPackages);
        }

        return $this->isQualifiedCovered($fqn, $allowlistedFqns, $ownPrefixes, $declaredPrefixes)
            || self::extensionDeclared($fqn, $declaredPackages);
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

    /**
     * @param string       $fqn              An already-loaded class or interface name.
     * @param list<string> $declaredPackages Every package name in the manifest's require: block.
     *
     * @return bool Whether it is built in and its extension is required as `ext-<name>`.
     */
    private static function extensionDeclared(string $fqn, array $declaredPackages): bool
    {
        if (!class_exists($fqn, false) && !interface_exists($fqn, false)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($fqn);
        $extension = $reflectionClass->getExtensionName();

        return is_string($extension) && in_array('ext-' . mb_strtolower($extension), $declaredPackages, true);
    }
}
