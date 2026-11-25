<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\MigratedTable;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The tables the modules' migrations create, as those migrations declare them
 * (#338), read without running them.
 */
interface MigratedTables
{
    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When no module migration creates it, or its migration cannot be read.
     *
     * @return MigratedTable The table as its creating migration declares it.
     */
    public function table(string $table): MigratedTable;

    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> The migrations that alter it after, each relative to the project.
     */
    public function alterers(string $table): array;
}
