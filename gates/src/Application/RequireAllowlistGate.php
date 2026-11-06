<?php

declare(strict_types=1);

namespace Gates\Application;

use JsonException;

use function array_any;
use function array_keys;
use function count;
use function file_get_contents;
use function fwrite;
use function is_file;
use function json_decode;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_ends_with;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #2 — composer `require` allow-list.
 *
 * AIC depends on no framework code (Symfony, Laravel, Yii, ...), and its
 * seams are PSR interfaces (CLAUDE.md, Stack). The require-checker (gate:3)
 * confirms every USED symbol has a backing require, but does NOT limit what
 * may be required in the first place. This gate holds the runtime `require`
 * of the root and `framework/composer.json` to `php`, `ext-*`, `psr/*` and
 * the framework itself.
 */
final readonly class RequireAllowlistGate
{
    /**
     * Permitted runtime require-key patterns.
     */
    private const array ALLOWED_PATTERNS = [
        '/^ignibyte\\/aic-framework$/',
        '/^psr\\//',
        '/^ext-/',
        '/^php$/',
    ];

    /**
     * Patterns allowed in `workflow/composer.json` only (#106): `ignibyte/aic-workflow` is
     * dev-only and never runs in production, its code index parses PHP with
     * php-parser, and its engine runs the gate suite of ignibyte/aic-gates (#116). The
     * runtime allow-list above is unchanged.
     */
    private const array WORKFLOW_PATTERNS = [
        '/^ignibyte\\/aic-gates$/',
        '/^nikic\\/php-parser$/',
    ];

    /**
     * Patterns allowed in `scaffold/composer.json` only (#279): `ignibyte/aic-scaffold` is a
     * Composer plugin, run by Composer at install time and never at run time, and
     * `composer-plugin-api` is the platform package Composer provides to its plugins.
     */
    private const array SCAFFOLD_PATTERNS = [
        '/^composer-plugin-api$/',
    ];

    /**
     * @param ProjectManifests $projectManifests The manifests the gate checks.
     */
    public function __construct(
        private ProjectManifests $projectManifests = new ProjectManifests(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     *
     * @throws JsonException When a manifest is malformed.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $manifests = $this->projectManifests->paths($root);
        $violations = [];

        foreach ($manifests as $manifest) {
            $manifestViolations = $this->inspectManifest($manifest, $stderr);

            if ($manifestViolations === null) {
                return 1;
            }

            $violations = [...$violations, ...$manifestViolations];
        }

        if ($violations !== []) {
            $this->reportViolations($violations, $stderr);

            return 1;
        }

        fwrite($stdout, sprintf('gate:2 — composer require allow-list: %d manifest(s) clean.%s', count($manifests), PHP_EOL));

        return 0;
    }

    /**
     * Inspect one manifest, returning its list of violations or `null` on read failure.
     *
     * @param string   $manifest Absolute path to a `composer.json`.
     * @param resource $stderr   Stream the read-failure message is written to.
     *
     * @throws JsonException When the manifest is malformed.
     *
     * @return list<string>|null
     */
    private function inspectManifest(string $manifest, mixed $stderr): ?array
    {
        if (!is_file($manifest)) {
            return [];
        }

        $raw = $this->readManifest($manifest);

        if ($raw === false) {
            fwrite($stderr, sprintf('gate:2 — cannot read %s%s', $manifest, PHP_EOL));

            return null;
        }

        $require = $this->extractRequireMap($raw);
        $violations = [];

        foreach (array_keys($require) as $package) {
            if ($this->permitted($manifest, $package)) {
                continue;
            }

            $violations[] = $manifest . ': ' . $package;
        }

        return $violations;
    }

    /**
     * @param string $manifest A manifest's path.
     * @param string $package  A package it requires.
     *
     * @return bool Whether the allow-list admits it: the runtime list for every manifest, the workflow's and the
     *              scaffold's for their own (#106, #279).
     */
    private function permitted(string $manifest, string $package): bool
    {
        return $this->isAllowed($package)
            || (str_ends_with($manifest, '/workflow/composer.json') && $this->matches($package, self::WORKFLOW_PATTERNS))
            || (str_ends_with($manifest, '/scaffold/composer.json') && $this->matches($package, self::SCAFFOLD_PATTERNS));
    }

    /**
     * Read `$manifest` with libxml-style error capture; returns the raw bytes or `false` on failure.
     *
     * @param string $manifest Absolute path to a `composer.json`.
     */
    private function readManifest(string $manifest): string|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return file_get_contents($manifest);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Decode the JSON manifest and return the `require` map (empty if missing).
     *
     * @param string $raw Raw `composer.json` contents.
     *
     * @throws JsonException When the manifest is not valid JSON.
     *
     * @return array<string, string>
     */
    private function extractRequireMap(string $raw): array
    {
        /** @var array{require?: array<string, string>} $parsed */
        $parsed = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return $parsed['require'] ?? [];
    }

    /**
     * Decide whether `$package` matches any allow-list pattern.
     *
     * @param string $package Composer require key.
     */
    private function isAllowed(string $package): bool
    {
        return $this->matches($package, self::ALLOWED_PATTERNS);
    }

    /**
     * @param string                 $package  A required package.
     * @param list<non-empty-string> $patterns Patterns it may match.
     *
     * @return bool Whether it matches one.
     */
    private function matches(string $package, array $patterns): bool
    {
        return array_any($patterns, static fn (string $pattern): bool => preg_match($pattern, $package) === 1);
    }

    /**
     * Emit the violation list + resolution guidance to `$stderr`.
     *
     * @param list<string> $violations Messages collected by {@see self::inspectManifest()}.
     * @param resource     $stderr     Stream to write to.
     */
    private function reportViolations(array $violations, mixed $stderr): void
    {
        fwrite($stderr, 'gate:2 — violation: composer require contains entries outside the allow-list.' . PHP_EOL);
        fwrite($stderr, 'Allowed: ignibyte/aic-framework, psr/*, ext-*, php' . PHP_EOL . PHP_EOL);

        foreach ($violations as $violation) {
            fwrite($stderr, sprintf('  - %s%s', $violation, PHP_EOL));
        }

        fwrite($stderr, PHP_EOL . 'Resolution:' . PHP_EOL);
        fwrite($stderr, '  - If the package is dev-only, move to require-dev (unrestricted).' . PHP_EOL);
        fwrite($stderr, '  - If genuinely required at runtime, propose an amendment to RequireAllowlistGate' . PHP_EOL);
        fwrite($stderr, '    + a recorded decision in docs/planning/plan.md justifying the new prefix.' . PHP_EOL);
    }
}
