<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\ProcessRunner;
use Gates\Domain\ProjectCopier;
use Override;
use RuntimeException;

use function bin2hex;
use function copy;
use function dirname;
use function explode;
use function file_exists;
use function is_dir;
use function is_link;
use function mkdir;
use function random_bytes;
use function readlink;
use function symlink;
use function sys_get_temp_dir;

/**
 * Copies the project for gate:33 (#300) the way the probes' scratch apps do
 * (#119): every file git lists, tracked or not ignored, copied, then `vendor/`,
 * `tools/` and the analysers' caches with `cp -a --reflink=auto`, which is a
 * cheap clone on a copy-on-write disk; and an empty git repository, since
 * gate:26 lists the files through git.
 */
final readonly class LocalProjectCopier implements ProjectCopier
{
    /**
     * What is copied whole beside the listed files, when it is there.
     */
    private const array WHOLE = ['vendor', 'tools', '.phpstan-cache', '.psalm-cache', '.rector-cache', '.native-gate'];

    /**
     * @param ProcessRunner $processRunner Runs git and cp.
     */
    public function __construct(
        private ProcessRunner $processRunner = new SystemProcessRunner(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @throws RuntimeException When git cannot list the files or a copy fails.
     *
     * @return string The copy's root, absolute.
     */
    #[Override]
    public function copy(string $root): string
    {
        $copy = sys_get_temp_dir() . '/aic-gate33-' . bin2hex(random_bytes(6));
        $processResult = $this->processRunner->run(['git', 'ls-files', '-co', '--exclude-standard', '-z'], $root);

        if ($processResult->exitCode !== 0 || !mkdir($copy, 0o700, true)) {
            throw new RuntimeException('gate:33 could not list the project or make ' . $copy . '.');
        }

        foreach (explode("\0", $processResult->stdout) as $file) {
            $this->file($root, $copy, $file);
        }

        foreach (self::WHOLE as $folder) {
            $this->whole($root . '/' . $folder, $copy);
        }

        $this->processRunner->run(['git', 'init', '-q'], $copy);

        return $copy;
    }

    /**
     * @param string $copy A copy's root.
     */
    #[Override]
    public function remove(string $copy): void
    {
        $this->processRunner->run(['rm', '-rf', '--', $copy]);
    }

    /**
     * @param string $root The project's root.
     * @param string $copy The copy's root.
     * @param string $file A file git lists, relative.
     *
     * @throws RuntimeException When it cannot be copied.
     */
    private function file(string $root, string $copy, string $file): void
    {
        $source = $root . '/' . $file;

        if ($file === '' || (!file_exists($source) && !is_link($source))) {
            return;
        }

        $target = $copy . '/' . $file;
        $folder = dirname($target);

        if (!$this->placed($source, $target, $folder)) {
            throw new RuntimeException('gate:33 could not copy ' . $file . '.');
        }
    }

    /**
     * @param string $source A file in the project, or a link.
     * @param string $target Where it goes in the copy.
     * @param string $folder The target's folder.
     *
     * @return bool Whether the folder is there or was made, and the file or link was copied.
     */
    private function placed(string $source, string $target, string $folder): bool
    {
        $made = is_dir($folder) || mkdir($folder, 0o700, true);

        return $made && (is_link($source)
            ? symlink((string) readlink($source), $target)
            : copy($source, $target));
    }

    /**
     * @param string $source A folder in the project.
     * @param string $copy   The copy's root.
     *
     * @throws RuntimeException When it is there and cannot be copied.
     */
    private function whole(string $source, string $copy): void
    {
        if (!is_dir($source)) {
            return;
        }

        $this->clone($source, $copy . '/');
    }

    /**
     * @param string $source What to copy.
     * @param string $target Where.
     *
     * @throws RuntimeException When cp fails.
     */
    private function clone(string $source, string $target): void
    {
        $processResult = $this->processRunner->run(['cp', '-a', '--reflink=auto', '--', $source, $target]);

        if ($processResult->exitCode !== 0) {
            throw new RuntimeException('gate:33 could not copy ' . $source . ': ' . $processResult->stderr);
        }
    }
}
