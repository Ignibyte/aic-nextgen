<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic queue:failed`: the jobs that failed, newest first, each with its
 * id, payload, attempts and reason (#208), so one can be queued again with
 * `queue:retry <id>`.
 */
final readonly class QueueFailedCommand implements Command
{
    /**
     * @param PostgresQueue $postgresQueue The queue and its failed jobs.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws QueryException           When the failed jobs cannot be read.
     * @throws InvalidArgumentException When the result is invalid.
     *
     * @return CommandResponse One line per failed job, or a line saying there is none.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic queue:failed'], 2);
        }

        $lines = $this->postgresQueue->failed();

        return new CommandResult($lines === [] ? ['queue:failed: no failed job.'] : $lines, 0);
    }
}
