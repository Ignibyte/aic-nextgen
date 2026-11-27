<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One job of an interpreted worker, as the build wires it (#336): the name it
 * is queued under, its class, and the arguments its constructor takes.
 */
final readonly class WorkerJob
{
    /**
     * @param string       $name      The name it is queued under, from its class (`StatementJob` → `statement`).
     * @param class-string $class     Its class.
     * @param list<string> $uses      The classes its arguments name.
     * @param list<string> $arguments Each constructor argument, as PHP over `$database` and `$root`.
     */
    public function __construct(
        public string $name,
        public string $class,
        public array $uses,
        public array $arguments,
    ) {
    }
}
