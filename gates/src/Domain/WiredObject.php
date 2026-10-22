<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_any;

/**
 * One node of a constructor graph the build resolved (#92, #93): a class built
 * from its arguments, a root the generated code reads from its context, a
 * plugin list, or a service a module factory makes.
 */
final readonly class WiredObject
{
    /**
     * The `root` of a plugin list: the generated code asks the context for the plugins tagged under `class`.
     */
    public const string TAGGED = 'tagged';

    /**
     * @param string            $class     The class built, the root's type, or a plugin list's type.
     * @param string            $root      The context accessor that gives the root (`appConfig`), `tagged` for a
     *                                     plugin list; empty for a built class.
     * @param list<WiredObject> $arguments The constructor's arguments, in order; for a factory's product, the factory.
     * @param string            $method    For a factory's product, the factory method that makes it; empty otherwise.
     */
    public function __construct(
        public string $class,
        public string $root,
        public array $arguments,
        public string $method = '',
    ) {
    }

    /**
     * @return list<string> The services built for its constructor, at every depth, once per occurrence; roots and
     *                      plugin lists are read, not built, so they are left out.
     */
    public function services(): array
    {
        $services = [];

        foreach ($this->arguments as $argument) {
            if ($argument->root !== '') {
                continue;
            }

            $services = [$argument->class, ...$services, ...$argument->services()];
        }

        return $services;
    }

    /**
     * @return bool Whether building it reads the context: it is a root or a plugin list, or one of its arguments
     *              reads one.
     */
    public function readsRoots(): bool
    {
        if ($this->root !== '') {
            return true;
        }

        return array_any($this->arguments, static fn (self $wiredObject): bool => $wiredObject->readsRoots());
    }
}
