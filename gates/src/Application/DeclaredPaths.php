<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\PresentationInventory;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;
use ReflectionClass;

use function array_diff;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match;
use function realpath;
use function scandir;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strrpos;
use function strtolower;
use function substr;

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
     * @return list<string> Its authored tpl sources in deterministic order; never a template selection mechanism.
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
     * @param PresentationInventory $presentationInventory The complete checked declaration inventory.
     *
     * @throws InvalidArgumentException When an old class is linked, unreadable or not owned by the template compiler.
     *
     * @return list<string> Existing retired component template classes; no framework or vendor template is selected.
     */
    public function legacy(PresentationInventory $presentationInventory): array
    {
        $orphans = [];

        foreach ($presentationInventory->templates as $binding) {
            if ($binding->role !== TemplateRole::Component) {
                continue;
            }

            $file = $this->legacyFile($binding->component);

            if (!$this->retired($file, substr($binding->component, (int) strrpos($binding->component, '\\') + 1))) {
                continue;
            }

            $orphans[] = $file;
        }

        return $orphans;
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
     * @return list<string> The recursively observed tpl sources.
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
     * @return list<string> Its tpl source or descendant tpl sources.
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

        return str_ends_with(strtolower($relative), '.tpl.php')
            ? [$relative]
            : [];
    }

    /**
     * @param class-string $component The validated component identity.
     *
     * @throws InvalidArgumentException When the actual component source is unavailable.
     *
     * @return string Its retired companion class path.
     */
    private function legacyFile(string $component): string
    {
        $source = (new ReflectionClass($component))->getFileName();

        if ($source === false) {
            throw new InvalidArgumentException($component . ': declared component source is unavailable.');
        }

        $short = substr($component, (int) strrpos($component, '\\') + 1);
        return dirname($source) . '/' . $short . 'Template.php';
    }

    /**
     * @param string $file      The old generated class beside its module's checked authored component.
     * @param string $component The component's canonical short name.
     *
     * @throws InvalidArgumentException When deleting the candidate would affect an unowned or linked source.
     *
     * @return bool Whether the existing compiler-owned class is retired.
     */
    private function retired(string $file, string $component): bool
    {
        if (is_link($file)) {
            throw new InvalidArgumentException($file . ': a retired template output cannot be a symlink.');
        }

        if (!is_file($file)) {
            return false;
        }

        $source = file_get_contents($file);

        if (
            $source === false
            || !str_contains($source, 'compiled from ' . $component . '.html.php by composer run build')
        ) {
            throw new InvalidArgumentException($file . ': refusing to remove a source not owned by the template compiler.');
        }

        return true;
    }
}
