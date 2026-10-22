<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;
use Throwable;

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

            $types = [...$types, ...$this->exceptionDocs->method($contextClass->roots->context, $entry->wrap)];
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
            return $this->exceptionDocs->method($wiringRoots->context, $wiredObject->root);
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
}
