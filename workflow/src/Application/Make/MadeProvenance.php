<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Console\ParsedArguments;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

use function hash;
use function hash_file;
use function implode;
use function is_dir;
use function is_file;
use function ksort;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Where a make's files came from (#302): its kind, the built-in's name
 * (`make:page` is `page`), the app kind's (`make poller` is `poller`) or
 * `scaffold` for make:scaffold; and its scaffold, the sha256 over the
 * folder whose templates it rendered (an app's `scaffolds/<kind>/` first,
 * then the package's; for make:scaffold, the `--from` kind's).
 */
final readonly class MadeProvenance
{
    /**
     * @param list<string> $folders Each `scaffolds/` folder, absolute, the app's first.
     */
    public function __construct(
        private array $folders,
    ) {
    }

    /**
     * @param string          $command         The make, such as `make:page` or `make`.
     * @param ParsedArguments $parsedArguments Its line.
     *
     * @return array{string, string} The kind, and the scaffold's hash, `sha256:<hex>`; empty when no folder holds it.
     */
    public function provenance(string $command, ParsedArguments $parsedArguments): array
    {
        $kind = match (true) {
            $command === 'make' => $parsedArguments->positional(0),
            str_starts_with($command, 'make:') => substr($command, strlen('make:')),
            default => $command,
        };
        $source = $kind === 'scaffold'
            ? $parsedArguments->flag('from')
            : $kind;

        return [$kind, $this->scaffold($source)];
    }

    /**
     * @param string $kind A kind.
     *
     * @return string The sha256 over its folder's files, each as its path and its own sha256; empty when no folder holds it.
     */
    private function scaffold(string $kind): string
    {
        foreach ($this->folders as $folder) {
            $hashes = $kind === ''
                ? []
                : $this->hashes($folder . '/' . $kind);

            if ($hashes !== []) {
                return 'sha256:' . hash('sha256', implode("\n", $hashes));
            }
        }

        return '';
    }

    /**
     * @param string $folder A kind's folder, absolute.
     *
     * @return array<string, string> Each file's path in it and its sha256, by path; empty when there is no folder.
     */
    private function hashes(string $folder): array
    {
        if (!is_dir($folder)) {
            return [];
        }

        $hashes = [];

        foreach (self::files($folder) as $file) {
            $hashes = [...$hashes, ...self::line($folder, $file)];
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @param string $folder A folder, absolute.
     *
     * @return list<string> Each file under it, absolute; empty when it cannot be read.
     */
    private static function files(string $folder): array
    {
        try {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
        } catch (UnexpectedValueException) {
            return [];
        }

        $files = [];

        for ($entries->rewind(); $entries->valid(); $entries->next()) {
            $entry = [$entries->current()];
            $files = $entry[0] instanceof SplFileInfo && is_file($entry[0]->getPathname())
                ? [...$files, $entry[0]->getPathname()]
                : $files;
        }

        return $files;
    }

    /**
     * @param string $folder A kind's folder.
     * @param string $path   A file in it.
     *
     * @return array<string, string> Its path in the folder and `<path> <sha256>`.
     */
    private static function line(string $folder, string $path): array
    {
        $relative = substr($path, strlen($folder) + 1);
        $hash = hash_file('sha256', $path);

        return [$relative => $relative . ' ' . ($hash === false ? '' : $hash)];
    }
}
