<?php

declare(strict_types=1);

namespace AIC\Schedule\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use JsonException;
use Override;

use function time;

/**
 * `bin/aic schedule:run`: queues the schedules due this minute (#47). A
 * systemd timer or cron runs it every minute; running it twice in a minute
 * queues nothing more.
 */
final readonly class ScheduleRunCommand implements Command
{
    /**
     * @param Scheduler $scheduler Queues what is due.
     */
    public function __construct(
        private Scheduler $scheduler,
    ) {
    }

    /**
     * @param CommandInput $commandInput Nothing: the command takes no arguments.
     *
     * @throws JsonException            When a payload cannot be encoded.
     * @throws QueryException           When the ledger or the queue fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse How many jobs it queued; the usage, exit 2, when given arguments.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic schedule:run'], 2);
        }

        return new CommandResult(['schedule:run queued ' . $this->scheduler->run(time()) . ' job(s).'], 0);
    }
}
