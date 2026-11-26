<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;

/**
 * The queue's commands (#46, #208), by name, for the framework's command list
 * (split out by #332): `queue:work` over every job, and `queue:failed` and
 * `queue:retry` over the failed ones.
 */
final readonly class QueueCommands
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param array<string, Job> $jobs Every job, by name.
     *
     * @return array<string, Command> Each command, by name.
     */
    public function byName(array $jobs): array
    {
        $postgresQueue = new PostgresQueue($this->database);

        return [
            'queue:failed' => new QueueFailedCommand(new QueueRecords($this->database)),
            'queue:retry' => new QueueRetryCommand($postgresQueue),
            'queue:work' => new QueueWorkCommand(new QueueWorker($postgresQueue, $jobs)),
        ];
    }
}
