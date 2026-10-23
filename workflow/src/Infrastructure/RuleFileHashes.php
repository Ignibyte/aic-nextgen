<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RuleFiles;
use AIC\Workflow\Domain\HouseRule;
use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function hash;
use function hash_file;
use function implode;
use function is_dir;
use function is_file;
use function sort;
use function strlen;
use function substr;

/**
 * The house rules' files on disk (#104, Droost's `RuleFiles::hash`):
 * `config/workflow.php`, each rule's file and each rule's probe directory,
 * by sha256; a directory's over its sorted `path NUL sha256` lines.
 */
final readonly class RuleFileHashes implements RuleFiles
{
    use DirectoryEntries;

    /**
     * @param string          $root       The project's root.
     * @param list<HouseRule> $houseRules The project's rules.
     */
    public function __construct(
        private string $root,
        private array $houseRules,
    ) {
    }

    /**
     * @return array<string, string|null> Each file's sha256 by its path; null for one that is missing.
     */
    #[Override]
    public function hashes(): array
    {
        $hashes = ['config/workflow.php' => $this->file('config/workflow.php')];

        foreach ($this->houseRules as $houseRule) {
            $hashes[$houseRule->run] = $this->file($houseRule->run);
            $hashes[$houseRule->probe()] = $this->directory($houseRule->probe());
        }

        return $hashes;
    }

    /**
     * @param string $path A file in the project.
     *
     * @return string|null Its sha256; null when it is no file.
     */
    private function file(string $path): ?string
    {
        $file = $this->root . '/' . $path;
        $hash = is_file($file)
            ? hash_file('sha256', $file)
            : false;

        return $hash === false
            ? null
            : $hash;
    }

    /**
     * @param string $path A directory in the project.
     *
     * @return string|null The sha256 of its sorted `path NUL sha256` lines; null when it is no directory.
     */
    private function directory(string $path): ?string
    {
        $directory = $this->root . '/' . $path;

        if (!is_dir($directory)) {
            return null;
        }

        $lines = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach (self::directoryEntries($files) as $file) {
            $line = self::line($file, $directory);

            if ($line === null) {
                return null;
            }

            $lines[] = $line;
        }

        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * @param SplFileInfo $file      A directory entry.
     * @param string      $directory The directory being fingerprinted.
     *
     * @return string|null Its relative-path/hash line, empty for a directory, null on an unreadable file.
     */
    private static function line(SplFileInfo $file, string $directory): ?string
    {
        if (!$file->isFile()) {
            return '';
        }

        $digest = hash_file('sha256', $file->getPathname());

        return $digest === false ? null : substr($file->getPathname(), strlen($directory) + 1) . "\0" . $digest;
    }
}
