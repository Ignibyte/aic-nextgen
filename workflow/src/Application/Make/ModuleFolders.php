<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

/**
 * The modules on disk (#122): each folder under `modules/` with its
 * manifest, as the build reads them.
 */
interface ModuleFolders
{
    /**
     * @return list<string> The modules' names, sorted.
     */
    public function names(): array;
}
