<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Queue\Domain\WorkerJobs;

/**
 * An interpreted worker's console (#336), which its `bin/aic.php` runs:
 * `queue:work [max]` over the worker's own queue, its name, with the jobs its
 * generated wiring builds.
 */
final readonly class WorkerKernel
{
    /**
     * @param Database   $database   The app's database.
     * @param string     $root       The app's root.
     * @param string     $queue      The worker's queue, its name.
     * @param WorkerJobs $workerJobs The worker's jobs.
     */
    public function __construct(
        private Database $database,
        private string $root,
        private string $queue,
        private WorkerJobs $workerJobs,
    ) {
    }

    /**
     * @return array<string, Command> The worker's commands, by name.
     */
    public function commands(): array
    {
        $jobs = $this->workerJobs->byName($this->database, $this->root);

        return [
            'queue:work' => new QueueWorkCommand(new QueueWorker(new PostgresQueue($this->database), $jobs, $this->queue)),
        ];
    }
}
