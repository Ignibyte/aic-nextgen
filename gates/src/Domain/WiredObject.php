<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One node of a constructor graph the build resolved (#92): a class built from
 * its arguments, or a root the generated code reads from the handler context.
 */
final readonly class WiredObject
{
    /**
     * @param string            $class     The class built, or the root's type.
     * @param string            $root      The `HandlerContext` accessor that gives the root (`appConfig`); empty for a built class.
     * @param list<WiredObject> $arguments The constructor's arguments, in order; empty for a root.
     */
    public function __construct(
        public string $class,
        public string $root,
        public array $arguments,
    ) {
    }
}
