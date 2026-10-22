<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The guard's wiring in `.claude/settings.json` (#105): its four hooks and
 * the Stop cap, merged into whatever else the file holds.
 */
interface HookWiring
{
    /**
     * @throws WorkflowRefusal When the file is there and is not settings init can merge into.
     */
    public function check(): void;

    /**
     * @throws WorkflowRefusal When the file cannot be written.
     *
     * @return list<InstallLine> One line for the hooks, one for the Stop cap.
     */
    public function wire(): array;
}
