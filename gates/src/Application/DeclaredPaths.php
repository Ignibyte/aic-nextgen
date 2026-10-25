<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function array_diff;
use function explode;
use function file_exists;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match;
use function realpath;
use function scandir;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

/** Resolve explicitly owned presentation files and audit authored templates without following links. */
final readonly class DeclaredPaths
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param string $folder The trusted project-relative owner folder.
     * @param string $source A portable path relative to that owner.
     * @param string $suffix The required source suffix, empty for declared public assets.
     *
     * @throws InvalidArgumentException When a source is missing, linked, nonportable or outside its owner.
     *
     * @return string The checked project-relative regular file.
     */
    public function file(string $folder, string $source, string $suffix = '.tpl.php'): string
    {
        $this->portable($folder);
        $this->portable($source);

        if (!str_ends_with($source, $suffix)) {
            throw new InvalidArgumentException($folder . '/' . $source . ': unexpected presentation source suffix.');
        }

        $relative = $folder . '/' . $source;
        $this->unlinked($relative);
        $base = realpath($this->root . '/' . $folder);
        $file = realpath($this->root . '/' . $relative);

        if ($base === false || $file === false || !is_file($file) || !str_starts_with($file, $base . '/')) {
            throw new InvalidArgumentException($relative . ': source must be a regular file inside its declared owner.');
        }

        return $relative;
    }

    /**
     * @param string $folder A project-relative authored template folder.
     *
     * @throws InvalidArgumentException When the folder contains a link or an unreadable entry.
     *
     * @return list<string> Its authored candidates in deterministic order; never a template selection mechanism.
     */
    public function templates(string $folder): array
    {
        $this->portable($folder);

        if (!file_exists($this->root . '/' . $folder) && !is_link($this->root . '/' . $folder)) {
            return [];
        }

        $this->unlinked($folder);
        $files = $this->under($folder);
        sort($files);

        return $files;
    }

    /**
     * @param string $path An owner-relative authored path.
     *
     * @throws InvalidArgumentException When any segment is empty, dot-prefixed or nonportable.
     */
    private function portable(string $path): void
    {
        foreach (explode('/', $path) as $segment) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]*\\z/', $segment) !== 1) {
                throw new InvalidArgumentException($path . ': presentation paths use portable lowercase, non-dot segments.');
            }
        }
    }

    /**
     * @param string $relative A path below the project root.
     *
     * @throws InvalidArgumentException When any segment is a symlink.
     */
    private function unlinked(string $relative): void
    {
        $path = $this->root;

        foreach (explode('/', $relative) as $segment) {
            $path .= '/' . $segment;

            if (is_link($path)) {
                throw new InvalidArgumentException($relative . ': presentation sources cannot traverse symlinks.');
            }
        }
    }

    /**
     * @param string $folder A project-relative folder with no linked ancestors.
     *
     * @throws InvalidArgumentException When the directory cannot be read or contains a link.
     *
     * @return list<string> The recursively observed authored candidates.
     */
    private function under(string $folder): array
    {
        $entries = is_dir($this->root . '/' . $folder)
            ? scandir($this->root . '/' . $folder)
            : false;

        if ($entries === false) {
            throw new InvalidArgumentException($folder . ': cannot read the authored template directory.');
        }

        $files = [];

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $files = [...$files, ...$this->entry($folder . '/' . $entry)];
        }

        return $files;
    }

    /**
     * @param string $relative One authored directory entry.
     *
     * @throws InvalidArgumentException When an entry is linked or not a regular file or directory.
     *
     * @return list<string> Its authored candidate or descendant candidates.
     */
    private function entry(string $relative): array
    {
        $path = $this->root . '/' . $relative;
        $this->unlinked($relative);

        if (is_dir($path)) {
            return $this->under($relative);
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException($relative . ': template entries must be regular files or directories.');
        }

        return $this->candidate($relative)
            ? [$relative]
            : [];
    }

    /**
     * @param string $relative A project-relative regular file, independent of the project's directory name.
     *
     * @return bool Whether it has a template suffix or is PHP inside an authored template folder.
     */
    private function candidate(string $relative): bool
    {
        $lower = strtolower($relative);

        return str_ends_with($lower, '.tpl.php') || str_ends_with($lower, '.html.php')
            || str_ends_with($lower, '.php')
                && preg_match('#^(?:modules/[^/]+/templates/|themes/[^/]+/layouts/|templates/|framework/templates/)#', $relative) === 1;
    }
}
