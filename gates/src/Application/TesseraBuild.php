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
use function trim;

use const ARRAY_FILTER_USE_BOTH;

/**
 * The pinned Tessera release in the public assets (#70, Tessera's
 * docs/consumers.md): each pinned file at build/assets/tessera-VERSION/,
 * checked against its SHA-256. A file already there with the right sum is
 * kept; a missing or changed one is downloaded from the release over plain
 * HTTPS with `curl` (or with `gh`, which also reaches a private repository)
 * and checked again, and a mismatch fails the build.
 */
final readonly class TesseraBuild
{
    /**
     * The repository the releases come from.
     */
    private const string REPOSITORY = 'Ignibyte/tessera';

    /**
     * Where a release's files are served, by tag and file name.
     */
    private const string DOWNLOADS = 'https://github.com/' . self::REPOSITORY . '/releases/download/';

    /**
     * @param string         $root           The app's root directory.
     * @param TesseraRelease $tesseraRelease The pin, from config/app.php.
     * @param ProcessRunner  $processRunner  Runs `curl`, then `gh release download` when curl fails.
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
     * @throws RuntimeException When neither `curl` nor `gh` can download the files.
     *
     * @return string A fresh directory holding the release's pinned files.
     */
    private function download(): string
    {
        $directory = sys_get_temp_dir() . '/aic-tessera-' . bin2hex(random_bytes(6));

        if (!mkdir($directory, 0o700)) {
            throw new RuntimeException('Tessera: cannot create ' . $directory . '.');
        }

        $curl = $this->attempt($this->curlCommand($directory));

        if ($curl === '') {
            return $directory;
        }

        $github = $this->attempt($this->ghCommand($directory));

        if ($github === '') {
            return $directory;
        }

        throw new RuntimeException('Tessera: the download failed. curl: ' . $curl . ' gh: ' . $github);
    }

    /**
     * @param list<string> $command A download command.
     *
     * @return string Empty when it succeeded; otherwise why it failed.
     */
    private function attempt(array $command): string
    {
        try {
            $processResult = $this->processRunner->run($command);
        } catch (RuntimeException $runtimeException) {
            return $runtimeException->getMessage();
        }

        return $processResult->exitCode === 0
            ? ''
            : 'exit ' . $processResult->exitCode . ', ' . trim($processResult->stderr);
    }

    /**
     * @param string $directory Where the files go.
     *
     * @return list<string> One `curl` fetching every pinned file over HTTPS.
     */
    private function curlCommand(string $directory): array
    {
        $base = self::DOWNLOADS . 'v' . $this->tesseraRelease->version . '/';
        $command = ['curl', '--fail', '--silent', '--show-error', '--location', '--proto', '=https', '--retry', '2'];

        foreach (array_keys($this->tesseraRelease->sums) as $name) {
            $command[] = '--output';
            $command[] = $directory . '/' . $name;
            $command[] = $base . $name;
        }

        return $command;
    }

    /**
     * @param string $directory Where the files go.
     *
     * @return list<string> One `gh release download` of every pinned file.
     */
    private function ghCommand(string $directory): array
    {
        $tag = 'v' . $this->tesseraRelease->version;
        $command = ['gh', 'release', 'download', $tag, '--repo', self::REPOSITORY, '--dir', $directory, '--clobber'];

        foreach (array_keys($this->tesseraRelease->sums) as $name) {
            $command[] = '--pattern';
            $command[] = $name;
        }

        return $command;
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
