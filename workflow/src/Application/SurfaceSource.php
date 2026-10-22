<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SurfaceEntry;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The project's `build/surface.json` (#101), as built now and as it was at a
 * commit, for the composition check.
 */
interface SurfaceSource
{
    /**
     * @param string|null $baseCommit A commit; null for the file as it is now.
     *
     * @throws WorkflowRefusal When the file is there and is not a surface manifest.
     *
     * @return array<string, SurfaceEntry> Every route, command and job, by key; empty when there is no file.
     */
    public function entries(?string $baseCommit): array;
}
