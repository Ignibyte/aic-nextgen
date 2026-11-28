<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

use function class_exists;
use function explode;
use function interface_exists;
use function method_exists;

/**
 * Follows the same constructor, factory and context calls as the wiring
 * writer, so generated methods describe the exceptions those calls declare.
 */
final readonly class ConstructionExceptions
{
    /**
     * @param ExceptionDocs $exceptionDocs Reads and normalizes method exception declarations.
     */
    public function __construct(private ExceptionDocs $exceptionDocs = new ExceptionDocs())
    {
    }

    /**
     * @param ContextClass $contextClass A generated class and its constructor graph.
     *
     * @throws InvalidArgumentException When a called method's exception contract is invalid.
     *
     * @return list<class-string<Throwable>> Every exception the generated method can propagate.
     */
    public function types(ContextClass $contextClass): array
    {
        $types = [];

        foreach ($contextClass->entries as $entry) {
            $types = [...$types, ...$this->object($entry->wiredObject, $contextClass->roots)];

            if ($entry->wrap === '') {
                continue;
            }

            $types = [...$types, ...$this->chain($contextClass->roots->context, $entry->wrap)];
        }

        return $this->exceptionDocs->normalized($types);
    }

    /**
     * @param WiredObject $wiredObject One node of the constructor graph.
     * @param WiringRoots $wiringRoots The context that supplies roots and tagged plugins.
     *
     * @throws InvalidArgumentException When a called method's exception contract is invalid.
     *
     * @return list<class-string<Throwable>> The node's own and its dependencies' exceptions.
     */
    private function object(WiredObject $wiredObject, WiringRoots $wiringRoots): array
    {
        if ($wiredObject->root !== '') {
            return $this->chain($wiringRoots->context, $wiredObject->root);
        }

        $factory = $wiredObject->arguments[0] ?? null;
        $types = $wiredObject->method !== '' && $factory instanceof WiredObject
            ? $this->exceptionDocs->method($factory->class, $wiredObject->method)
            : $this->exceptionDocs->method($wiredObject->class, '__construct');

        foreach ($wiredObject->arguments as $argument) {
            $types = [...$types, ...$this->object($argument, $wiringRoots)];
        }

        return $this->exceptionDocs->normalized($types);
    }

    /**
     * @param string $class The context class.
     * @param string $path  A root's accessor or a wrap: one method, or a chain of them (`push()->broadcaster`,
     *                      #32), each step on the class the step before returns.
     *
     * @throws InvalidArgumentException When a called method's exception contract is invalid.
     *
     * @return list<class-string<Throwable>> What every step documents.
     */
    private function chain(string $class, string $path): array
    {
        $types = [];
        $owner = $class;

        foreach (explode('()->', $path) as $method) {
            $types = [...$types, ...$this->exceptionDocs->method($owner, $method)];
            $owner = $this->returned($owner, $method);
        }

        return $types;
    }

    /**
     * @param string $class  A class.
     * @param string $method One of its methods.
     *
     * @return string The class it returns; the class itself when it returns none, which the next step then misses.
     */
    private function returned(string $class, string $method): string
    {
        $known = class_exists($class) || interface_exists($class);
        $returnType = $known && method_exists($class, $method)
            ? (new ReflectionMethod($class, $method))->getReturnType()
            : null;

        return $returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()
            ? $returnType->getName()
            : $class;
    }
}
