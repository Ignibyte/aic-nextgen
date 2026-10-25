<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function basename;
use function explode;
use function file_exists;
use function glob;
use function is_dir;
use function is_link;
use function preg_match;
use function sort;
use function str_contains;
use function substr;

/** Audit historical compiler-owned companions for retirement without selecting any old template input. */
final readonly class RetiredTemplates
{
    /**
     * @param string                $root                  The project root.
     * @param QuietFileReader       $quietFileReader       Reads candidates without warning output.
     * @param ClassReferenceScanner $classReferenceScanner Checks their actual declared identity.
     */
    public function __construct(
        private string $root,
        private QuietFileReader $quietFileReader = new QuietFileReader(),
        private ClassReferenceScanner $classReferenceScanner = new ClassReferenceScanner(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules Actual validated modules, including ones whose old layout was removed.
     *
     * @throws InvalidArgumentException When a candidate or its directory is linked, unreadable or falsely marked as compiler-owned.
     *
     * @return list<string> Compiler-owned old outputs, sorted; no source is written or deleted here.
     */
    public function files(array $modules): array
    {
        $files = [];

        foreach ($modules as $module) {
            $files = [...$files, ...$this->module($module)];
        }

        sort($files);

        return $files;
    }

    /**
     * @param ModuleDefinition $moduleDefinition One actual module.
     *
     * @throws InvalidArgumentException When its old outputs cannot be safely classified.
     *
     * @return list<string> Its historical compiler outputs; unrelated authored classes are left alone.
     */
    private function module(ModuleDefinition $moduleDefinition): array
    {
        $directory = $this->directory($moduleDefinition->name);

        if (!is_dir($directory)) {
            return $this->absent($directory);
        }

        $candidates = glob($directory . '/*Template.php');

        if ($candidates === false) {
            throw new InvalidArgumentException($directory . ': cannot audit retired template outputs.');
        }

        return $this->owned($moduleDefinition, $candidates);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The expected owner.
     * @param list<string>     $candidates       Files in its exact Component directory.
     *
     * @throws InvalidArgumentException When a marked candidate is not a real compiler-owned output.
     *
     * @return list<string> The checked retired paths.
     */
    private function owned(ModuleDefinition $moduleDefinition, array $candidates): array
    {
        $files = [];

        foreach ($candidates as $candidate) {
            if (!$this->candidate($moduleDefinition, $candidate)) {
                continue;
            }

            $files[] = $candidate;
        }

        return $files;
    }

    /**
     * @param string $module The validated portable module name.
     *
     * @throws InvalidArgumentException When its name or any owning directory is unsafe.
     *
     * @return string Its exact unlinked Component directory.
     */
    private function directory(string $module): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*\\z/', $module) !== 1) {
            throw new InvalidArgumentException('A retired output needs a portable module owner.');
        }

        $path = $this->root;

        foreach (explode('/', 'modules/' . $module . '/src/Component') as $part) {
            $path .= '/' . $part;

            if (is_link($path)) {
                throw new InvalidArgumentException($path . ': retired output ownership cannot traverse symlinks.');
            }
        }

        return $path;
    }

    /**
     * @param string $directory A nonexistent or obstructed Component directory.
     *
     * @throws InvalidArgumentException When a file obstructs the expected directory.
     *
     * @return list<string> No old outputs when the directory is genuinely absent.
     */
    private function absent(string $directory): array
    {
        if (file_exists($directory)) {
            throw new InvalidArgumentException($directory . ': retired outputs require a real directory.');
        }

        return [];
    }

    /**
     * @param ModuleDefinition $moduleDefinition The expected module namespace.
     * @param string           $file             One old companion candidate.
     *
     * @throws InvalidArgumentException When it is linked, unreadable or falsely marked.
     *
     * @return bool Whether it is an actual historical compiler output, including an unbound old layout.
     */
    private function candidate(ModuleDefinition $moduleDefinition, string $file): bool
    {
        if (is_link($file)) {
            throw new InvalidArgumentException($file . ': a retired output cannot be a symlink.');
        }

        $source = $this->quietFileReader->read($file)
            ?? throw new InvalidArgumentException($file . ': cannot read a retired output candidate.');

        if (
            !str_contains($source, 'compiled from ')
            && !str_contains($source, 'edit the template and run composer run build')
        ) {
            return false;
        }

        return $this->compilerOwned($moduleDefinition, $file, $source);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The expected namespace owner.
     * @param string           $file             The exact historical output path.
     * @param string           $source           Its readable source.
     *
     * @throws InvalidArgumentException When provenance, class identity or shape does not match the compiler.
     *
     * @return true Only the actual compiler's old companion shape may be removed.
     */
    private function compilerOwned(ModuleDefinition $moduleDefinition, string $file, string $source): true
    {
        $short = basename($file, '.php');
        $component = substr($short, 0, -8);
        $marker = $component . "'s markup, compiled from " . $component . '.html.php by composer run build (#97, #98):';

        if (
            !str_contains($source, $marker)
            || !str_contains($source, 'final readonly class ' . $short)
            || $this->classReferenceScanner->scan($source)->declared !== $moduleDefinition->namespace . 'Component\\' . $short
        ) {
            throw new InvalidArgumentException($file . ': refusing to retire source not owned by the template compiler.');
        }

        return true;
    }
}
