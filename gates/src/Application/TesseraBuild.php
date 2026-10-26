<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Tessera\Domain\TesseraRelease;
use Gates\Domain\BuildPaths;
use Gates\Domain\ProcessRunner;
use RuntimeException;

use function array_filter;
use function array_keys;
use function basename;
use function bin2hex;
use function file_get_contents;
use function hash_file;
use function is_file;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

use const ARRAY_FILTER_USE_BOTH;

/**
 * The pinned Tessera release in the public assets (#70, Tessera's
 * docs/consumers.md): each pinned file at build/assets/tessera-VERSION/,
 * checked against its SHA-256. A file already there with the right sum is
 * kept; a missing or changed one is downloaded from the release with `gh`
 * and checked again, and a mismatch fails the build.
 */
final readonly class TesseraBuild
{
    /**
     * The repository the releases come from.
     */
    private const string REPOSITORY = 'Ignibyte/tessera';

    /**
     * @param string         $root           The app's root directory.
     * @param TesseraRelease $tesseraRelease The pin, from config/app.php.
     * @param ProcessRunner  $processRunner  Runs `gh release download`.
     */
    public function __construct(
        private string $root,
        private TesseraRelease $tesseraRelease,
        private ProcessRunner $processRunner,
    ) {
    }

    /**
     * @throws RuntimeException When a file cannot be downloaded or does not match its pinned sum.
     *
     * @return array<string, string> Each file's content by its path under build/assets.
     */
    public function files(): array
    {
        $directory = $this->root . '/' . BuildPaths::ASSETS . '/' . $this->tesseraRelease->directory();
        $sums = $this->tesseraRelease->sums;
        $missing = array_filter(
            $sums,
            static fn (string $sum, string $name): bool => self::verified($directory . '/' . $name, $sum) === '',
            ARRAY_FILTER_USE_BOTH,
        );
        $source = $missing === []
            ? $directory
            : $this->download();
        $outputs = [];

        foreach ($sums as $name => $sum) {
            $outputs[$directory . '/' . $name] = $this->content($source . '/' . $name, $sum);
        }

        return $outputs;
    }

    /**
     * @param string $path A pinned file, in the assets or in a fresh download.
     * @param string $sum  Its pinned SHA-256.
     *
     * @throws RuntimeException When it does not match.
     *
     * @return string Its content.
     */
    private function content(string $path, string $sum): string
    {
        $content = self::verified($path, $sum);

        if ($content === '') {
            throw new RuntimeException(
                'Tessera ' . $this->tesseraRelease->version . ': ' . basename($path) . ' does not match its pinned SHA-256.',
            );
        }

        return $content;
    }

    /**
     * @throws RuntimeException When `gh` fails.
     *
     * @return string A fresh directory holding the release's pinned files.
     */
    private function download(): string
    {
        $directory = sys_get_temp_dir() . '/aic-tessera-' . bin2hex(random_bytes(6));

        if (!mkdir($directory, 0o700)) {
            throw new RuntimeException('Tessera: cannot create ' . $directory . '.');
        }

        $command = ['gh', 'release', 'download', 'v' . $this->tesseraRelease->version, '--repo', self::REPOSITORY, '--dir', $directory];

        foreach (array_keys($this->tesseraRelease->sums) as $name) {
            $command[] = '--pattern';
            $command[] = $name;
        }

        $processResult = $this->processRunner->run($command);

        if ($processResult->exitCode !== 0) {
            throw new RuntimeException('Tessera: gh release download failed: ' . $processResult->stderr);
        }

        return $directory;
    }

    /**
     * @param string $path A file.
     * @param string $sum  Its pinned SHA-256, hex.
     *
     * @return string Its content when it exists and matches; empty otherwise.
     */
    private static function verified(string $path, string $sum): string
    {
        if (!is_file($path) || hash_file('sha256', $path) !== $sum) {
            return '';
        }

        $content = file_get_contents($path);

        return $content === false
            ? ''
            : $content;
    }
}
