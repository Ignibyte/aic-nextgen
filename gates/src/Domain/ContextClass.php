<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_any;

/**
 * A generated per-module class built from a context (#93): `<M>Handlers`,
 * `<M>Commands`, `<M>Jobs` or `<M>Middleware`. It holds the context and has one
 * method returning its entries, keyed or as a list.
 */
final readonly class ContextClass
{
    /**
     * @param string                $class        Its short name (`FaqHandlers`).
     * @param WiringRoots           $roots        The side it is built for: its context and roots.
     * @param string                $method       Its method (`byId`).
     * @param string                $returnDoc    The method's return type in phpdoc (`array<string, RequestHandlerInterface>`).
     * @param string                $what         What the method returns, for its docblock.
     * @param list<string>          $docTypes     The classes the return type and the prelude name, to import or count.
     * @param list<ContextEntry>    $entries      Its entries.
     * @param array<string, string> $seeds        The services another generated class builds (#257): each one's code
     *                                            by class, used as its local; none for a class that builds its own.
     * @param string                $prelude      The statement that makes that class; empty with no seeds.
     * @param bool                  $preludeRoots Whether the prelude hands it the context.
     */
    public function __construct(
        public string $class,
        public WiringRoots $roots,
        public string $method,
        public string $returnDoc,
        public string $what,
        public array $docTypes,
        public array $entries,
        public array $seeds = [],
        public string $prelude = '',
        public bool $preludeRoots = false,
    ) {
    }

    /**
     * @return bool Whether its entries are keyed, which PHPCS wants sorted; a list keeps its order.
     */
    public function keyed(): bool
    {
        return $this->entries !== [] && $this->entries[0]->key !== '';
    }

    /**
     * @return array<string, int> How many times each service is an argument in its entries' constructor graphs; one
     *                            used more than once is built once, into a local.
     */
    public function uses(): array
    {
        $uses = [];

        foreach ($this->entries as $entry) {
            foreach ($entry->wiredObject->services() as $service) {
                $uses[$service] = ($uses[$service] ?? 0) + 1;
            }
        }

        return $uses;
    }

    /**
     * @return bool Whether some entry reads the context; when none does, the class takes no context (#93).
     */
    public function readsRoots(): bool
    {
        return $this->preludeRoots
            || array_any($this->entries, static fn (ContextEntry $contextEntry): bool => $contextEntry->readsRoots());
    }
}
