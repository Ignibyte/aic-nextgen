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

use function gmdate;
use function preg_match;
use function strtotime;
use function time;

/**
 * `bin/aic schedule:run [YYYY-MM-DDTHH:MM]`: queues the schedules due this
 * minute (#47), or due in the given UTC minute (#208), so a test can show a
 * cadence queue its job. A systemd timer or cron runs it every minute;
 * running it twice for a minute queues nothing more.
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
     * @param CommandInput $commandInput Nothing, or one UTC minute as `YYYY-MM-DDTHH:MM`.
     *
     * @throws JsonException            When a payload cannot be encoded.
     * @throws QueryException           When the ledger or the queue fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse How many jobs it queued; the usage, exit 2, for any other arguments.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $minute = self::minute($commandInput->argument(0, ''));

        if ($minute === null || $commandInput->argument(1, '') !== '') {
            return new CommandResult(['usage: bin/aic schedule:run [YYYY-MM-DDTHH:MM]'], 2);
        }

        return new CommandResult(['schedule:run queued ' . $this->scheduler->run($minute) . ' job(s).'], 0);
    }

    /**
     * @param string $given The argument: empty, or a UTC minute.
     *
     * @return int|null The minute's timestamp, now for none; null when it is not a real minute.
     */
    private static function minute(string $given): ?int
    {
        if ($given === '') {
            return time();
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $given) !== 1) {
            return null;
        }

        $timestamp = strtotime($given . ':00+00:00');

        return $timestamp !== false && gmdate('Y-m-d\\TH:i', $timestamp) === $given
            ? $timestamp
            : null;
    }
}
