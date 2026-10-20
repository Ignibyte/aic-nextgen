<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Contracts\Job;
use App\Job\DemoJob;

/**
 * The app's jobs by name (#46): the queue worker's registry, and the list
 * build/surface.json shows. A job is queued by its name, never its class.
 */
final readonly class AppJobs
{
    /**
     * @return array<string, Job> The jobs by name.
     */
    public function byName(): array
    {
        return ['demo' => new DemoJob()];
    }
}
