<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Queue\Domain\FailedJob;
use AIC\Queue\Domain\JobState;
use AIC\Queue\Domain\QueuedJob;
use AIC\Queue\Domain\QueueState;

use function array_map;

/**
 * The queue as typed records (#262): the jobs waiting, running or retrying,
 * and the failed record, newest first, so an app shows them without SQL of
 * its own, and one job's state by the id it was queued under (#336).
 * `PostgresQueue` writes both tables.
 */
final readonly class QueueRecords
{
    /** A time as ISO 8601 in UTC. */
    private const string ISO = "'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"'";

    /**
     * @param Database $database The database, with the job tables (CreateJobTables).
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param int $limit The most to list.
     *
     * @throws QueryException When the queue cannot be read.
     *
     * @return list<QueuedJob> The jobs in the queue, newest first.
     */
    public function queued(int $limit = 50): array
    {
        return array_map(
            self::queuedJob(...),
            $this->database->fetchAll(
                'SELECT id, job, payload, attempts,'
                . " CASE WHEN reserved_at >= now() - interval '10 minutes' THEN 'running'"
                . " WHEN attempts > 0 THEN 'retrying' ELSE 'waiting' END AS state,"
                . " to_char(available_at AT TIME ZONE 'UTC', " . self::ISO . ') AS due_at,'
                . " coalesce(to_char(reserved_at AT TIME ZONE 'UTC', " . self::ISO . "), '') AS reserved_at"
                . ' FROM aic_jobs ORDER BY id DESC LIMIT ?',
                [$limit],
            )->all(),
        );
    }

    /**
     * @param int $id The id `PostgresQueue::dispatchTo()` answered.
     *
     * @throws QueryException When the queue cannot be read.
     *
     * @return JobState Running while a worker holds it, Queued while it waits, Failed in the failed record, and
     *                  Done once no record holds it.
     */
    public function state(int $id): JobState
    {
        $state = $this->database->fetchOne(
            "SELECT coalesce((SELECT CASE WHEN reserved_at >= now() - interval '10 minutes' THEN 'running'"
            . " ELSE 'queued' END FROM aic_jobs WHERE id = ?),"
            . " (SELECT 'failed' FROM aic_failed_jobs WHERE job_id = ? LIMIT 1), 'done') AS state",
            [$id, $id],
        )->string('state');

        return match ($state) {
            'running' => JobState::Running,
            'queued' => JobState::Queued,
            'failed' => JobState::Failed,
            default => JobState::Done,
        };
    }

    /**
     * @param int $limit The most to list.
     *
     * @throws QueryException When the record cannot be read.
     *
     * @return list<FailedJob> The failed jobs, newest first.
     */
    public function failed(int $limit = 20): array
    {
        return array_map(
            self::failedJob(...),
            $this->database->fetchAll(
                'SELECT id, job, payload, attempts, reason,'
                . " to_char(failed_at AT TIME ZONE 'UTC', " . self::ISO . ') AS failed_at'
                . ' FROM aic_failed_jobs ORDER BY id DESC LIMIT ?',
                [$limit],
            )->all(),
        );
    }

    /**
     * @param Row $row A row of `aic_jobs`, its state and times worked out.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return QueuedJob The job.
     */
    private static function queuedJob(Row $row): QueuedJob
    {
        return new QueuedJob(
            $row->int('id'),
            $row->string('job'),
            $row->string('payload'),
            self::queueState($row->string('state')),
            $row->int('attempts'),
            $row->string('due_at'),
            $row->string('reserved_at'),
        );
    }

    /**
     * @param Row $row A row of `aic_failed_jobs`, its time worked out.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return FailedJob The failed job.
     */
    private static function failedJob(Row $row): FailedJob
    {
        return new FailedJob(
            $row->int('id'),
            $row->string('job'),
            $row->string('payload'),
            $row->int('attempts'),
            $row->string('reason'),
            $row->string('failed_at'),
        );
    }

    /**
     * @param string $name The state SQL worked out.
     *
     * @return QueueState Its case.
     */
    private static function queueState(string $name): QueueState
    {
        return match ($name) {
            'running' => QueueState::Running,
            'retrying' => QueueState::Retrying,
            default => QueueState::Waiting,
        };
    }
}
