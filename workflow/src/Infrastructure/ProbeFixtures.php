<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RuleFixtures;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\RuleCase;
use AIC\Workflow\Domain\RuleChange;
use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function basename;
use function copy;
use function dirname;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function rmdir;
use function sort;
use function str_ends_with;
use function strlen;
use function substr;
use function unlink;

use const GLOB_BRACE;
use const GLOB_ONLYDIR;

/**
 * A house rule's probe laid out for a run of it (#104): `base/` and each
 * `held-*`/`broken-*` overlay, `.fixture` stripped from every name (so no
 * tool reads a fixture as code), a `<path>.deleted` marker deleting. Each
 * case becomes a base tree and a head tree under the work directory (each
 * file named `<path>.case`, so no tool reads it as code), and its changes
 * are the paths its overlay names.
 */
final readonly class ProbeFixtures implements RuleFixtures
{
    /**
     * What a laid-out file's name ends in, so no tool reads a case as code.
     */
    private const string LAID = '.case';

    /**
     * @param string     $root       The project's root.
     * @param string     $work       Where the cases are laid out, absolute; overwritten on every run.
     * @param AtomicFile $atomicFile Makes directories.
     */
    public function __construct(
        private string $root,
        private string $work,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param HouseRule $houseRule The rule.
     *
     * @return list<RuleCase>|null Its cases; null when it ships no probe directory.
     */
    #[Override]
    public function cases(HouseRule $houseRule): ?array
    {
        $probe = $this->root . '/' . $houseRule->probe();

        if (!is_dir($probe)) {
            return null;
        }

        $cases = [];
        $directories = glob($probe . '/{held,broken}-*', GLOB_BRACE | GLOB_ONLYDIR);

        foreach ($directories === false ? [] : $directories as $directory) {
            $cases[] = $this->laid($houseRule, $probe, basename($directory));
        }

        return $cases;
    }

    /**
     * @param HouseRule $houseRule The rule.
     * @param string    $probe     Its probe directory.
     * @param string    $name      A case.
     *
     * @return RuleCase The case, laid out.
     */
    private function laid(HouseRule $houseRule, string $probe, string $name): RuleCase
    {
        $base = $this->work . '/' . $houseRule->id . '/' . $name . '/base';
        $head = $this->work . '/' . $houseRule->id . '/' . $name . '/head';
        $this->copyTree($probe . '/base', $base);
        $this->copyTree($probe . '/base', $head);
        $changes = [];

        foreach ($this->fixtures($probe . '/' . $name) as $relative) {
            $path = $this->path($relative);
            $this->overlay($probe . '/' . $name . '/' . $relative, $head . '/' . $path . self::LAID, str_ends_with($relative, '.deleted'));
            $changes = [...$changes, ...$this->change($path, $base, $head)];
        }

        return new RuleCase($name, $changes);
    }

    /**
     * @param string $path A path the overlay names.
     * @param string $base The case's base tree.
     * @param string $head Its head tree.
     *
     * @return list<RuleChange> Its change; none when the overlay left it as the base had it, as a run's diff would.
     */
    private function change(string $path, string $base, string $head): array
    {
        $before = is_file($base . '/' . $path . self::LAID)
            ? $base . '/' . $path . self::LAID
            : null;
        $after = is_file($head . '/' . $path . self::LAID)
            ? $head . '/' . $path . self::LAID
            : null;
        $same = $before !== null && $after !== null && file_get_contents($before) === file_get_contents($after);

        return $same
            ? []
            : [new RuleChange($path, $before, $after)];
    }

    /**
     * @param string $from   An overlay file.
     * @param string $target Its place in the head tree.
     * @param bool   $delete Whether it is a deletion marker.
     */
    private function overlay(string $from, string $target, bool $delete): void
    {
        if ($delete) {
            unlink($target);

            return;
        }

        $this->atomicFile->directory(dirname($target));
        copy($from, $target);
    }

    /**
     * @param string $from   A fixture tree.
     * @param string $target Where its files go, `.fixture` stripped.
     */
    private function copyTree(string $from, string $target): void
    {
        $this->clear($target);

        foreach ($this->fixtures($from) as $relative) {
            $this->overlay($from . '/' . $relative, $target . '/' . $this->path($relative) . self::LAID, false);
        }
    }

    /**
     * @param string $directory A fixture tree.
     *
     * @return list<string> Its fixture files, relative to it, sorted.
     */
    private function fixtures(string $directory): array
    {
        $files = [];

        foreach ($this->walk($directory, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $files = str_ends_with($relative, '.fixture') || str_ends_with($relative, '.deleted')
                ? [...$files, $relative]
                : $files;
        }

        sort($files);

        return $files;
    }

    /**
     * @param string $relative A fixture's name.
     *
     * @return string The path it stands for.
     */
    private function path(string $relative): string
    {
        return substr($relative, 0, -8);
    }

    /**
     * @param string $directory A work directory.
     */
    private function clear(string $directory): void
    {
        foreach ($this->walk($directory, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }
    }

    /**
     * @param string $directory A directory, perhaps not there.
     * @param 0|1|2  $mode      `RecursiveIteratorIterator::LEAVES_ONLY` for files, `CHILD_FIRST` for everything, deepest first.
     *
     * @return list<SplFileInfo> What it holds; nothing when it is not there.
     */
    private function walk(string $directory, int $mode): array
    {
        $found = [];

        if (!is_dir($directory)) {
            return [];
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), $mode) as $file) {
            $found = $file instanceof SplFileInfo
                ? [...$found, $file]
                : $found;
        }

        return $found;
    }
}
