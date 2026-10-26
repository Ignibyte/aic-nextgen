<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\Psr4Map;
use Gates\Domain\RegexCaptures;
use RuntimeException;

use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function array_values;
use function class_implements;
use function class_parents;
use function class_uses;
use function dirname;
use function file_get_contents;
use function in_array;
use function realpath;
use function str_contains;

/**
 * Collects a runtime class's class-graph closure, in dependency order (gate:21).
 *
 * Ported from the elephc bundler: walk from the root through every class its
 * file names that resolves to a file, then order the files so a dependency
 * comes before its dependents. A cycle (two classes naming each other in type
 * hints) is broken where the walk meets it; PHP and elephc load type hints
 * lazily, so the order only matters for `extends`, `implements` and traits.
 * A class whose supertype is still being placed waits for a later visit, so
 * a cycle through a supertype (`Html implements Component`, whose method
 * returns `Html`) still loads the supertype first.
 *
 * PHP built-ins that composer may back with a `symfony/polyfill` stub stay
 * out: elephc provides them itself, and the stubs read host-only constants.
 */
final readonly class ClosureBundler
{
    use RegexCaptures;

    /**
     * Built-in interfaces and classes a polyfill may shadow.
     */
    private const array BUILTIN_TYPES = [
        'Stringable', 'UnitEnum', 'BackedEnum', 'JsonSerializable', 'Countable', 'Traversable',
        'Iterator', 'IteratorAggregate', 'ArrayAccess', 'Throwable', 'Attribute',
    ];

    /**
     * @param ClassReferenceScanner $classReferenceScanner Reads the class references out of a file.
     */
    public function __construct(
        private ClassReferenceScanner $classReferenceScanner = new ClassReferenceScanner(),
    ) {
    }

    /**
     * The files of `$root`'s closure, dependencies first.
     *
     * @param string  $root    FQCN of the runtime class.
     * @param Psr4Map $psr4Map Resolves class names to files.
     *
     * @throws RuntimeException When the root or a file in its closure cannot be read.
     *
     * @return list<string> Absolute file paths, in load order.
     */
    public function closure(string $root, Psr4Map $psr4Map): array
    {
        $graph = $this->walk($root, $psr4Map);
        $ordered = [];

        foreach (array_keys($graph['files']) as $fqcn) {
            $ordered = $this->visit($fqcn, $graph['deps'], $ordered, []);
        }

        $files = [];

        foreach ($ordered as $fqcn) {
            $files[] = $graph['files'][$fqcn];
        }

        return $files;
    }

    /**
     * The templates a closure's files require by a literal path (#155): a template is no class, so it joins the
     * native gate's cache key here, not the bundle.
     *
     * @param list<string> $files A closure's files.
     *
     * @throws RuntimeException When a file cannot be read.
     *
     * @return list<string> The required templates, once each, in file order.
     */
    public function templates(array $files): array
    {
        $templates = [];

        foreach ($files as $file) {
            foreach ($this->required($file, $this->read($file)) as $template) {
                $templates[$template] = true;
            }
        }

        return array_keys($templates);
    }

    /**
     * Breadth-first walk of the class graph from `$root`.
     *
     * @param string  $root    FQCN of the runtime class.
     * @param Psr4Map $psr4Map Resolves class names to files.
     *
     * @throws RuntimeException When a file in the closure cannot be read.
     *
     * @return array{files: array<string, string>, deps: array<string, list<string>>} FQCN => file, FQCN => resolved dependencies.
     */
    private function walk(string $root, Psr4Map $psr4Map): array
    {
        $files = [];
        $deps = [];
        $queue = [$root];

        while ($queue !== []) {
            $fqcn = array_shift($queue);
            $file = $psr4Map->fileFor($fqcn);

            if (array_key_exists($fqcn, $files) || $file === null || $this->isBuiltin($fqcn, $file)) {
                continue;
            }

            $files[$fqcn] = $file;
            $deps[$fqcn] = $this->resolvedDependencies($fqcn, $file, $psr4Map);
            $queue = [...$queue, ...$deps[$fqcn]];
        }

        if (!array_key_exists($root, $files)) {
            throw new RuntimeException('gate:21 — ' . $root . ' does not resolve to a file through PSR-4.');
        }

        return ['files' => $files, 'deps' => $deps];
    }

    /**
     * The class names one file refers to that resolve to a file, itself excluded.
     *
     * @param string  $fqcn    The file's own class.
     * @param string  $file    Absolute file path.
     * @param Psr4Map $psr4Map Resolves class names to files.
     *
     * @throws RuntimeException When the file cannot be read.
     *
     * @return list<string> Resolved dependency FQCNs.
     */
    private function resolvedDependencies(string $fqcn, string $file, Psr4Map $psr4Map): array
    {
        $resolved = [];

        foreach ($this->candidates($file) as $candidate) {
            if (
                $candidate === $fqcn
                || $psr4Map->fileFor($candidate) === null
                || in_array($candidate, $resolved, true)
            ) {
                continue;
            }

            $resolved[] = $candidate;
        }

        return $resolved;
    }

    /**
     * @param string $file A class file.
     *
     * @throws RuntimeException When it or a template it requires cannot be read.
     *
     * @return list<string> The class names it and the templates it requires name (#155).
     */
    private function candidates(string $file): array
    {
        $source = $this->read($file);
        $candidates = $this->classReferenceScanner->scan($source)->candidates;

        foreach ($this->required($file, $source) as $template) {
            $candidates = [...$candidates, ...$this->classReferenceScanner->scan($this->read($template))->candidates];
        }

        return $candidates;
    }

    /**
     * @param string $file   A class file.
     * @param string $source Its source.
     *
     * @return list<string> The `.phtml` templates it requires by `__DIR__ . '/…'`, absolute.
     */
    private function required(string $file, string $source): array
    {
        $templates = [];

        foreach (self::regexColumn('#\\brequire\\s+__DIR__\\s*\\.\\s*\'(/[^\']+\\.phtml)\'#', $source, 1) as $relative) {
            $path = dirname($file) . $relative;
            $real = realpath($path);
            $templates[] = $real === false
                ? $path
                : $real;
        }

        return $templates;
    }

    /**
     * @param string $file A file.
     *
     * @throws RuntimeException When it cannot be read.
     *
     * @return string Its contents.
     */
    private function read(string $file): string
    {
        $source = file_get_contents($file);

        if ($source === false) {
            throw new RuntimeException('gate:21 — cannot read ' . $file);
        }

        return $source;
    }

    /**
     * Depth-first post-order visit: dependencies are appended before the class itself.
     *
     * @param string                      $fqcn    Class to place.
     * @param array<string, list<string>> $deps    Resolved dependencies per class.
     * @param list<string>                $ordered Classes placed so far.
     * @param list<string>                $path    Classes on the current walk, to break cycles.
     *
     * @return list<string> Classes placed, `$fqcn` included.
     */
    private function visit(string $fqcn, array $deps, array $ordered, array $path): array
    {
        if (in_array($fqcn, $ordered, true) || in_array($fqcn, $path, true) || !array_key_exists($fqcn, $deps)) {
            return $ordered;
        }

        if (array_intersect($this->supertypes($fqcn), $path) !== []) {
            return $ordered;
        }

        foreach ($deps[$fqcn] as $dependency) {
            $ordered = $this->visit($dependency, $deps, $ordered, [...$path, $fqcn]);
        }

        return [...$ordered, $fqcn];
    }

    /**
     * @param string $fqcn A class in the closure.
     *
     * @return list<string> Its parents, interfaces and traits, which PHP must declare before it.
     */
    private function supertypes(string $fqcn): array
    {
        $parents = class_parents($fqcn);
        $interfaces = class_implements($fqcn);
        $traits = class_uses($fqcn);

        return [
            ...array_values($parents === false ? [] : $parents),
            ...array_values($interfaces === false ? [] : $interfaces),
            ...array_values($traits === false ? [] : $traits),
        ];
    }

    /**
     * Whether a resolved class is a PHP built-in or a polyfill stub elephc provides itself.
     *
     * @param string $fqcn Resolved class name.
     * @param string $file Its resolved file.
     *
     * @return bool True to keep it out of the closure.
     */
    private function isBuiltin(string $fqcn, string $file): bool
    {
        return in_array($fqcn, self::BUILTIN_TYPES, true) || str_contains($file, '/vendor/symfony/polyfill');
    }
}
