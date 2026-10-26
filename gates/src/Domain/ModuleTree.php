<?php

declare(strict_types=1);

namespace Gates\Domain;

use function explode;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * What gate:26 checks (#94), read once: the modules from their manifests, every
 * path git knows (tracked and untracked, ignored ones left out), the symbols of
 * each PHP file, and the PSR-4 prefixes
 * composer.json declares and the map that resolves them.
 */
final readonly class ModuleTree
{
    /**
     * @param list<ModuleDefinition>       $modules  The modules, in name order.
     * @param list<string>                 $paths    Every path git lists, relative to the repo root.
     * @param array<string, SourceSymbols> $symbols  Each PHP file's symbols, by relative path.
     * @param array<string, list<string>>  $prefixes composer.json's PSR-4 prefixes and their folders, as written.
     * @param Psr4Map                      $psr4Map  Resolves a class to its file.
     * @param string                       $root     The repo root, absolute.
     */
    public function __construct(
        public array $modules,
        public array $paths,
        public array $symbols,
        public array $prefixes,
        public Psr4Map $psr4Map,
        public string $root,
    ) {
    }

    /**
     * @param string $path A path relative to the repo root.
     *
     * @return string|null The class the file declares; null for a file that declares none.
     */
    public function declared(string $path): ?string
    {
        $sourceSymbols = $this->symbols[$path] ?? null;

        return $sourceSymbols instanceof SourceSymbols
            ? $sourceSymbols->declared
            : null;
    }

    /**
     * @param string $path A path relative to the repo root.
     *
     * @return ModuleDefinition|null The module whose folder holds it, if any.
     */
    public function moduleOf(string $path): ?ModuleDefinition
    {
        if (!str_starts_with($path, 'modules/')) {
            return null;
        }

        $name = explode('/', $path)[1] ?? '';

        foreach ($this->modules as $module) {
            if ($module->name === $name) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param string $class A class name.
     *
     * @return string|null The class's file relative to the repo root, when PSR-4 maps it to one in the repo.
     */
    public function pathOf(string $class): ?string
    {
        $file = $this->psr4Map->fileFor($class);

        return $file !== null && str_starts_with($file, $this->root . '/')
            ? substr($file, strlen($this->root) + 1)
            : null;
    }
}
