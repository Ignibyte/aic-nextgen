<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One of gate:26's module boundary rules (#94): it reads the module tree and
 * names every breach.
 */
interface ModuleRule
{
    /**
     * @param ModuleTree $moduleTree The modules, their files and the repository's paths.
     *
     * @return list<BoundaryViolation> Every breach of the rule; none when it holds.
     */
    public function check(ModuleTree $moduleTree): array;
}
