<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Job;

/**
 * The modules' queue jobs (#93), for the console kernel; the generated wiring
 * implements it when some module declares a job.
 */
interface JobWiring
{
    /**
     * @param ConsoleContext $consoleContext The console's roots.
     *
     * @return array<string, Job> The modules' jobs by name.
     */
    public function jobs(ConsoleContext $consoleContext): array;
}
