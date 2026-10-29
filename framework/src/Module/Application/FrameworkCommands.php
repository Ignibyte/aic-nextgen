<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;
use AIC\Queue\Application\PostgresQueue;
use AIC\Queue\Application\QueueFailedCommand;
use AIC\Queue\Application\QueueRetryCommand;
use AIC\Queue\Application\QueueWorkCommand;
use AIC\Queue\Application\QueueWorker;
use AIC\Schedule\Application\Scheduler;
use AIC\Schedule\Application\ScheduleRunCommand;
use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Application\MigrateCommand;
use AIC\Schema\Application\MigrationStatusCommand;
use AIC\Schema\Application\Migrator;
use AIC\Schema\Application\PostgresDdl;
use AIC\Schema\Contracts\Migration;

/**
 * The framework's own console commands (#6, #13, placed by #93): `migrate` and
 * `migrate:status` over every migration, `queue:work` over every job,
 * `queue:failed` and `queue:retry` over the failed ones (#208), and
 * `schedule:run` over every schedule.
 */
final readonly class FrameworkCommands
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param list<Migration>    $migrations The framework's migrations, then the modules', oldest first.
     * @param array<string, Job> $jobs       Every job by name.
     * @param list<Schedule>     $schedules  Every schedule.
     *
     * @return array<string, Command> The framework's commands by name.
     */
    public function byName(array $migrations, array $jobs, array $schedules): array
    {
        $migrator = new Migrator($this->database, new PostgresDdl(), $migrations);
        $postgresQueue = new PostgresQueue($this->database);

        return [
            'migrate' => new MigrateCommand($migrator),
            'migrate:status' => new MigrationStatusCommand($migrator),
            'queue:failed' => new QueueFailedCommand($postgresQueue),
            'queue:retry' => new QueueRetryCommand($postgresQueue),
            'queue:work' => new QueueWorkCommand(new QueueWorker($postgresQueue, $jobs)),
            'schedule:run' => new ScheduleRunCommand(new Scheduler($this->database, $postgresQueue, $schedules)),
        ];
    }
}
