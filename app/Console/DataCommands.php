<?php

declare(strict_types=1);

namespace App\Console;

use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Queue\Application\PostgresQueue;
use AIC\Queue\Application\QueueWorkCommand;
use AIC\Queue\Application\QueueWorker;
use App\AppJobs;
use App\User\UserRepository;

/**
 * The commands that work on the app's data: `user:add` (#9) and
 * `queue:work` (#46).
 */
final readonly class DataCommands
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @return array<string, Command> The commands by name.
     */
    public function byName(): array
    {
        $queueWorker = new QueueWorker(new PostgresQueue($this->database), (new AppJobs())->byName());

        return [
            'queue:work' => new QueueWorkCommand($queueWorker),
            'user:add' => new UserAdd(new UserRepository($this->database), new PgcryptoPasswordHasher($this->database)),
        ];
    }
}
