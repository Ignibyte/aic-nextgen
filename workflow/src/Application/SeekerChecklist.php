<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The AIC checklist the seeker reads by (#103): its item ids, which every
 * finding cites, and where it is.
 */
interface SeekerChecklist
{
    /**
     * @throws WorkflowRefusal When the checklist cannot be read.
     *
     * @return list<string> Its item ids, such as `A1` and `L2`, in order.
     */
    public function ids(): array;

    /**
     * @return string Its path, relative to the project.
     */
    public function path(): string;
}
