<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Job;

/**
 * The modules' queue jobs when none of them reads a root (#93), for the
 * console kernel; the generated wiring implements it instead of `JobWiring`,
 * so no context goes unread.
 */
interface RootlessJobWiring
{
    /**
     * @return array<string, Job> The modules' jobs by name.
     */
    public function jobs(): array;
}
