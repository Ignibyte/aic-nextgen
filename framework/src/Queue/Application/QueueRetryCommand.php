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

use function ctype_digit;
use function strlen;

/**
 * `bin/aic queue:retry <id>`: queues a failed job again, its attempts from 0
 * (#208). The id is the one `queue:failed` prints.
 */
final readonly class QueueRetryCommand implements Command
{
    /**
     * @param PostgresQueue $postgresQueue The queue and its failed jobs.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @param CommandInput $commandInput The failed job's id.
     *
     * @throws QueryException           When the queue cannot be changed.
     * @throws InvalidArgumentException When the result is invalid.
     *
     * @return CommandResponse 0 when it is queued again, 1 when no failed job has the id, 2 for a usage error.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $id = $commandInput->argument(0, '');

        if (!ctype_digit($id) || strlen($id) > 18 || $commandInput->argument(1, '') !== '') {
            return new CommandResult(['usage: bin/aic queue:retry <id>'], 2);
        }

        return $this->postgresQueue->retry((int) $id)
            ? new CommandResult(['queue:retry queued failed job ' . $id . ' again.'], 0)
            : new CommandResult(['queue:retry: no failed job ' . $id . '.'], 1);
    }
}
