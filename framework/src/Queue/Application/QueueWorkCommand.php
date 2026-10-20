<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use Override;

use function ctype_digit;

/**
 * `bin/aic queue:work [max]`: runs due jobs one at a time, at most `max`
 * (100 by default), and exits when none is due (#46). A systemd service that
 * restarts it, or a timer, keeps the queue moving.
 */
final readonly class QueueWorkCommand implements Command
{
    /**
     * @param QueueWorker $queueWorker Runs the jobs.
     */
    public function __construct(
        private QueueWorker $queueWorker,
    ) {
    }

    /**
     * @param CommandInput $commandInput The most jobs to run, first.
     *
     * @throws QueryException When the queue cannot be read or changed.
     *
     * @return CommandResult How many ran; exit 2 for a max that is not a number.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $max = $commandInput->argument(0, '100');

        if (!ctype_digit($max)) {
            return new CommandResult(['usage: bin/aic queue:work [max]'], 2);
        }

        return new CommandResult(['queue:work ran ' . $this->queueWorker->work((int) $max) . ' job(s).'], 0);
    }
}
