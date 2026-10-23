<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\RegexCaptures;
use InvalidArgumentException;
use ReflectionMethod;
use Throwable;

use function array_any;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function class_exists;
use function explode;
use function file_get_contents;
use function implode;
use function interface_exists;
use function is_a;
use function is_string;
use function ltrim;
use function max;
use function method_exists;
use function str_pad;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * Exception contracts used by generated wiring: read the called method's
 * declaration, resolve its names, and keep the smallest equivalent union.
 */
final readonly class ExceptionDocs
{
    use RegexCaptures;

    /**
     * @param string $class  The class whose method the generated code calls.
     * @param string $method The called method, including __construct.
     *
     * @throws InvalidArgumentException When a declared exception name cannot be resolved.
     *
     * @return list<class-string<Throwable>> The documented exception types.
     */
    public function method(string $class, string $method): array
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException('A construction contract names an unknown class: ' . $class);
        }

        if (!method_exists($class, $method)) {
            return [];
        }

        $reflectionMethod = new ReflectionMethod($class, $method);
        $file = $reflectionMethod->getFileName();
        $source = is_string($file)
            ? file_get_contents($file)
            : false;

        $doc = (string) $reflectionMethod->getDocComment();

        return $this->read(
            $doc,
            is_string($source) ? $source : '',
            $reflectionMethod->getDeclaringClass()->getNamespaceName(),
        );
    }

    /**
     * @param string $source A freshly generated class, never loaded from disk.
     *
     * @throws InvalidArgumentException When a declared exception name cannot be resolved.
     *
     * @return list<class-string<Throwable>> Its method exception contracts.
     */
    public function source(string $source): array
    {
        return $this->read($source, $source, 'App\\Generated');
    }

    /**
     * @param list<class-string<Throwable>> $types The possible exception types.
     *
     * @return list<class-string<Throwable>> Unique types, excluding subclasses already covered by a parent.
     */
    public function normalized(array $types): array
    {
        return array_values(array_filter(
            array_values(array_unique($types)),
            static fn (string $type): bool => !array_any(
                $types,
                static fn (string $parent): bool => $type !== $parent && is_a($type, $parent, true),
            ),
        ));
    }

    /**
     * @param list<class-string<Throwable>> $types The exception types, imported by the caller.
     *
     * @return string Their method docblock lines, empty when none can escape.
     */
    public function block(array $types): string
    {
        $lines = [];
        $names = array_map($this->short(...), $this->normalized($types));
        $width = max([0, ...array_map(strlen(...), $names)]);

        foreach ($names as $name) {
            $lines[] = '     * @throws ' . str_pad($name, $width) . ' When a called constructor or service fails.';
        }

        return $lines === []
            ? ''
            : implode("\n", $lines) . "\n     *\n";
    }

    /**
     * @param string                        $method One generated method, including its docblock.
     * @param list<class-string<Throwable>> $types  The called aggregator's exceptions.
     *
     * @return string The method with its propagated exception contracts.
     */
    public function append(string $method, array $types): string
    {
        if ($types === []) {
            return $method;
        }

        $offset = strpos($method, '     * @return');
        $offset = $offset === false
            ? (int) strpos($method, '     */')
            : $offset;

        return substr($method, 0, $offset) . $this->block($types) . substr($method, $offset);
    }

    /**
     * @param string $doc       The method's docblock, or generated class source.
     * @param string $source    The declaring class's source, for imports.
     * @param string $namespace The declaring namespace.
     *
     * @throws InvalidArgumentException When an exception declaration does not name a Throwable.
     *
     * @return list<class-string<Throwable>> Its resolved exception types.
     */
    private function read(string $doc, string $source, string $namespace): array
    {
        $names = self::regexColumn('/@throws\\s+([\\\\\\w|]+)/', $doc, 1);
        $types = [];

        if ($names === []) {
            return [];
        }

        foreach (explode('|', implode('|', $names)) as $name) {
            $type = $this->resolve($name, $source, $namespace);

            if (!is_a($type, Throwable::class, true)) {
                throw new InvalidArgumentException('An exception contract does not name a Throwable: ' . $type);
            }

            $types[] = $type;
        }

        return $this->normalized($types);
    }

    /**
     * @param string $name      An exception as written in its docblock.
     * @param string $source    The declaring class's source, with flat class imports.
     * @param string $namespace The declaring namespace.
     *
     * @return string Its fully qualified class name.
     */
    private function resolve(string $name, string $source, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        foreach ($this->imports($source) as [$alias, $qualified]) {
            if ($name === $alias || str_starts_with($name, $alias . '\\')) {
                return $qualified . substr($name, strlen($alias));
            }
        }

        return ltrim($namespace . '\\' . $name, '\\');
    }

    /**
     * @param string $source PHP source with flat class imports.
     *
     * @return list<array{string, string}> Each alias and its qualified name.
     */
    private function imports(string $source): array
    {
        $matches = self::regexRows('/^use ([\\\\\\w]+)(?: as (\\w+))?;/m', $source);
        $imports = [];

        foreach ($matches as $match) {
            if (!array_key_exists(1, $match)) {
                continue;
            }

            $qualified = $match[1];
            $imports[] = [$match[2] ?? $this->short($qualified), $qualified];
        }

        return $imports;
    }

    /**
     * @param string $type A fully qualified class name.
     *
     * @return string Its final name segment, for imports and aligned exception tags.
     */
    private function short(string $type): string
    {
        return substr($type, (int) strrpos('\\' . $type, '\\'));
    }
}
