<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessResult;
use Gates\Domain\ProcessRunner;
use Gates\Domain\RegexCaptures;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function is_string;
use function preg_match;
use function sort;
use function sprintf;
use function strlen;
use function substr;

/**
 * An app's browser tests (#174): Playwright specs, run with aic/gates' one
 * config from the app's root, at the pinned version. The tests an app keeps
 * live in `tests/browser/`; gate:23 runs them all, and `aic-gate browser`
 * runs the ones a look names.
 */
final readonly class BrowserTests
{
    use RegexCaptures;

    /**
     * The Playwright the stubs pin (`gates/stubs/package.json`), whose Chromium the box keeps.
     */
    public const string PLAYWRIGHT = '1.62.1';

    /**
     * Where an app keeps its browser tests, relative to its root.
     */
    public const string FOLDER = 'tests/browser';

    /**
     * A browser test's file name.
     */
    private const string SPEC = '/\\.spec\\.(?:ts|js|mjs)\\z/';

    /**
     * @param ProcessRunner   $processRunner   Runs Playwright.
     * @param string          $package         aic/gates' own root, where the config is.
     * @param QuietFileReader $quietFileReader Reads the installed Playwright's version.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $package,
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The app's root.
     *
     * @return list<string> Every kept browser test, relative to the root, sorted.
     */
    public function kept(string $root): array
    {
        $folder = $root . '/' . self::FOLDER;

        if (!is_dir($folder)) {
            return [];
        }

        $specs = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof SplFileInfo || !self::spec($file)) {
                continue;
            }

            $specs[] = substr($file->getPathname(), strlen($root) + 1);
        }

        sort($specs);

        return $specs;
    }

    /**
     * @param string $root The app's root.
     *
     * @return string|null Why Playwright cannot run there; null when it is installed at the pin.
     */
    public function unready(string $root): ?string
    {
        $manifest = $this->quietFileReader->read($root . '/node_modules/@playwright/test/package.json') ?? '';
        $version = self::regexMatch('/"version"\\s*:\\s*"([^"]+)"/', $manifest)[1] ?? null;

        return match (true) {
            !is_string($version) => 'Playwright is not installed: run npm ci in the project root (vendor/bin/aic-gate tools does).',
            $version !== self::PLAYWRIGHT => sprintf(
                'Playwright %s is installed, not the pinned %s: run npm ci in the project root.',
                $version,
                self::PLAYWRIGHT,
            ),
            default => null,
        };
    }

    /**
     * @param string                $root    The app's root.
     * @param string                $baseUrl The site the tests run against.
     * @param array<string, string> $env     The environment, with the gate user's login when there is one.
     * @param list<string>          $specs   The tests to run, relative to the root; none runs every kept one.
     *
     * @return ProcessResult Playwright's exit and output.
     */
    public function run(string $root, string $baseUrl, array $env, array $specs): ProcessResult
    {
        $config = $this->package . '/browser/playwright.config.mjs';
        $command = [$root . '/node_modules/.bin/playwright', 'test', '--config', $config, ...$specs];
        $env['AIC_BROWSER_BASE_URL'] = $baseUrl;
        // gate:23 points XDG_CACHE_HOME at the compiler's caches; the browsers stay in the user's Playwright cache.
        $env['PLAYWRIGHT_BROWSERS_PATH'] ??= ($env['HOME'] ?? '') . '/.cache/ms-playwright';

        return $this->processRunner->run($command, $root, $env);
    }

    /**
     * @param SplFileInfo $splFileInfo A file under the tests' folder.
     *
     * @return bool Whether it is a browser test.
     */
    private static function spec(SplFileInfo $splFileInfo): bool
    {
        return $splFileInfo->isFile() && preg_match(self::SPEC, $splFileInfo->getFilename()) === 1;
    }
}
