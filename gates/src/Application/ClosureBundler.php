<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\Psr4Map;
use RuntimeException;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function file_get_contents;
use function in_array;
use function str_contains;

/**
 * Collects a runtime class's class-graph closure, in dependency order (gate:21).
 *
 * Ported from the elephc bundler: walk from the root through every class its
 * file names that resolves to a file, then order the files so a dependency
 * comes before its dependents. A cycle (two classes naming each other in type
 * hints) is broken where the walk meets it; PHP and elephc load type hints
 * lazily, so the order only matters for `extends`, `implements` and traits.
 *
 * PHP built-ins that composer may back with a `symfony/polyfill` stub stay
 * out: elephc provides them itself, and the stubs read host-only constants.
 *
 * @api
 */
final readonly class ClosureBundler
{
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
        $source = file_get_contents($file);

        if ($source === false) {
            throw new RuntimeException('gate:21 — cannot read ' . $file);
        }

        $resolved = [];

        foreach ($this->classReferenceScanner->scan($source)->candidates as $candidate) {
            if ($candidate === $fqcn || $psr4Map->fileFor($candidate) === null) {
                continue;
            }

            $resolved[] = $candidate;
        }

        return $resolved;
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

        foreach ($deps[$fqcn] as $dependency) {
            $ordered = $this->visit($dependency, $deps, $ordered, [...$path, $fqcn]);
        }

        return [...$ordered, $fqcn];
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
