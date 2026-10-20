<?php

declare(strict_types=1);

namespace AIC\Schedule\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use AIC\Schedule\Domain\Schedule;
use JsonException;

use function array_map;
use function array_sum;

/**
 * Queues the schedules due in a minute (#47), each once: a schedule's row in
 * the ledger is claimed with `ON CONFLICT DO NOTHING`, and only the run that
 * claims it queues the job. The queue worker runs it.
 */
final readonly class Scheduler
{
    /**
     * @param Database       $database      The database, with the ledger (CreateScheduleRuns).
     * @param PostgresQueue  $postgresQueue The queue.
     * @param list<Schedule> $schedules     The app's schedules.
     */
    public function __construct(
        private Database $database,
        private PostgresQueue $postgresQueue,
        private array $schedules,
    ) {
    }

    /**
     * @param int $now A Unix time in the minute to run.
     *
     * @throws JsonException  When a payload cannot be encoded.
     * @throws QueryException When the ledger or the queue fails.
     *
     * @return int How many jobs it queued.
     */
    public function run(int $now): int
    {
        $minute = $now - $now % 60;

        return array_sum(array_map(fn (Schedule $schedule): int => $this->queueIfDue($schedule, $minute), $this->schedules));
    }

    /**
     * @param string $name A schedule's name.
     *
     * @throws QueryException When the ledger cannot be read.
     *
     * @return string The last minute it fired, `YYYY-MM-DD HH:MM` UTC; empty when never.
     */
    public function lastRun(string $name): string
    {
        return $this->database->fetchOne(
            "SELECT coalesce(to_char(max(minute) AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI'), '') AS last"
            . ' FROM aic_schedule_runs WHERE name = ?',
            [$name],
        )->string('last');
    }

    /**
     * @param Schedule $schedule A schedule.
     * @param int      $minute   The minute's Unix time.
     *
     * @throws JsonException  When the payload cannot be encoded.
     * @throws QueryException When the ledger or the queue fails.
     *
     * @return int 1 when it was due and this run claimed it, else 0.
     */
    private function queueIfDue(Schedule $schedule, int $minute): int
    {
        if (!$schedule->cadence->matches($minute)) {
            return 0;
        }

        $claimed = $this->database->execute(
            'INSERT INTO aic_schedule_runs (name, minute) VALUES (?, to_timestamp(?)) ON CONFLICT DO NOTHING',
            [$schedule->name, $minute],
        );

        if ($claimed === 0) {
            return 0;
        }

        $this->postgresQueue->dispatch($schedule->job, $schedule->payload, 0);

        return 1;
    }
}
