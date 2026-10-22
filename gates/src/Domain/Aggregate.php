<?php

declare(strict_types=1);

namespace Gates\Domain;

use function count;
use function max;
use function min;

/**
 * One of the generated aggregators that merge the modules' results (#93):
 * `ModuleHandlers`, `ModuleCommands`, `ModuleJobs`, `ModuleMiddleware`,
 * `ModuleSchedules`, `ModulePrefixes` or `ModuleMigrations`. Its members are
 * the modules' generated classes of one kind, each with the same method.
 */
final readonly class Aggregate
{
    /**
     * @param string             $root      The root class's short name (`ModuleCommands`).
     * @param string             $method    The members' and its own method (`byName`).
     * @param string             $returnDoc The method's return type in phpdoc.
     * @param string             $what      What the method returns, for its docblock.
     * @param list<class-string> $docTypes  The classes the return type names, to import.
     * @param WiringRoots|null   $roots     The context it passes its members; null when they take none.
     */
    public function __construct(
        public string $root,
        public string $method,
        public string $returnDoc,
        public string $what,
        public array $docTypes,
        public ?WiringRoots $roots,
    ) {
    }

    /**
     * @return positive-int The most members one part merges: PHPMD's coupling of 15, less its fixed types and the
     *                      next part, and no more than PHPCS's 20 lines leave for `array_merge(…)`.
     */
    public function partSize(): int
    {
        $fixed = count($this->docTypes) + ($this->roots instanceof WiringRoots ? 1 : 0);

        return max(1, min(17, 15 - $fixed - 1));
    }
}
