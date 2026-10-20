<?php

declare(strict_types=1);

namespace App\Console;

use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use AIC\Queue\Application\PostgresQueue;
use AIC\Queue\Application\QueueWorkCommand;
use AIC\Queue\Application\QueueWorker;
use AIC\Schedule\Application\Scheduler;
use AIC\Schedule\Application\ScheduleRunCommand;
use App\AppJobs;
use App\AppSchedules;
use App\User\UserRepository;

/**
 * The commands that work on the app's data: `user:add` (#9), `queue:work`
 * (#46) and `schedule:run` (#47).
 */
final readonly class DataCommands
{
    /**
     * @param Database      $database      The database.
     * @param MailTransport $mailTransport Sends the notification job's mail.
     * @param MailConfig    $mailConfig    The sender's address.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
    ) {
    }

    /**
     * @return array<string, Command> The commands by name.
     */
    public function byName(): array
    {
        $postgresQueue = new PostgresQueue($this->database);
        $queueWorker = new QueueWorker($postgresQueue, (new AppJobs($this->database, $this->mailTransport, $this->mailConfig))->byName());
        $scheduler = new Scheduler($this->database, $postgresQueue, (new AppSchedules())->all());

        return [
            'queue:work' => new QueueWorkCommand($queueWorker),
            'schedule:run' => new ScheduleRunCommand($scheduler),
            'user:add' => new UserAdd(new UserRepository($this->database), new PgcryptoPasswordHasher($this->database)),
        ];
    }
}
