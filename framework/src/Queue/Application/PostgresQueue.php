<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Domain\ReservedJob;
use AIC\Session\Domain\StringMapJson;
use JsonException;

/**
 * The job queue in Postgres (#46). Workers take jobs with `FOR UPDATE SKIP
 * LOCKED`, so several can share the table; a reservation older than ten
 * minutes belongs to a worker that died, and the job is due again.
 */
final readonly class PostgresQueue
{
    /**
     * @param Database      $database      The database, with the job tables (CreateJobTables).
     * @param StringMapJson $stringMapJson Encodes payloads.
     */
    public function __construct(
        private Database $database,
        private StringMapJson $stringMapJson = new StringMapJson(),
    ) {
    }

    /**
     * @param string                $job          The job's name in the registry.
     * @param array<string, string> $values       Its payload.
     * @param int                   $delaySeconds How long it waits before it is due.
     *
     * @throws JsonException  When the payload cannot be encoded.
     * @throws QueryException When the job cannot be stored.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function dispatch(string $job, array $values, int $delaySeconds): void
    {
        $this->database->execute(
            'INSERT INTO aic_jobs (job, payload, attempts, available_at) VALUES (?, ?, 0, now() + make_interval(secs => ?))',
            [$job, $this->stringMapJson->encode($values), $delaySeconds],
        );
    }

    /**
     * Takes the oldest due job and counts the attempt.
     *
     * @throws QueryException When the queue cannot be read.
     *
     * @return ReservedJob|null The job; null when none is due.
     */
    public function reserve(): ?ReservedJob
    {
        $row = $this->database->fetchOne(
            'WITH taken AS (UPDATE aic_jobs SET reserved_at = now(), attempts = attempts + 1 WHERE id = ('
            . 'SELECT id FROM aic_jobs WHERE available_at <= now()'
            . " AND (reserved_at IS NULL OR reserved_at < now() - interval '10 minutes')"
            . ' ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1) RETURNING id, job, payload, attempts)'
            . " SELECT coalesce(max(id), 0) AS id, coalesce(max(job), '') AS job,"
            . " coalesce(max(payload), '') AS payload, coalesce(max(attempts), 0) AS attempts FROM taken",
            [],
        );

        return $row->int('id') === 0
            ? null
            : new ReservedJob($row->int('id'), $row->string('job'), $row->string('payload'), $row->int('attempts'));
    }

    /**
     * @param int $id A reserved job, done.
     *
     * @throws QueryException When it cannot be removed.
     */
    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM aic_jobs WHERE id = ?', [$id]);
    }

    /**
     * @param int $id           A reserved job to try again.
     * @param int $delaySeconds How long it waits.
     *
     * @throws QueryException When it cannot be released.
     */
    public function release(int $id, int $delaySeconds): void
    {
        $this->database->execute(
            'UPDATE aic_jobs SET reserved_at = NULL, available_at = now() + make_interval(secs => ?) WHERE id = ?',
            [$delaySeconds, $id],
        );
    }

    /**
     * Moves a job to the failed record, in one transaction.
     *
     * @param ReservedJob $reservedJob The job.
     * @param string      $reason      Why it failed.
     *
     * @throws QueryException When it cannot be moved.
     */
    public function bury(ReservedJob $reservedJob, string $reason): void
    {
        $this->database->beginTransaction();
        $this->database->execute(
            'INSERT INTO aic_failed_jobs (job, payload, attempts, reason, failed_at) VALUES (?, ?, ?, ?, now())',
            [$reservedJob->job, $reservedJob->payload, $reservedJob->attempts, $reason],
        );
        $this->delete($reservedJob->id);
        $this->database->commit();
    }

    /**
     * @param int $id A failed job's id.
     *
     * @throws QueryException When the queue cannot be changed.
     *
     * @return bool Whether it was failed and is queued again now, with its attempts from 0 (#208).
     */
    public function retry(int $id): bool
    {
        $this->database->beginTransaction();
        $queued = $this->database->execute(
            'INSERT INTO aic_jobs (job, payload, attempts, available_at) SELECT job, payload, 0, now() FROM aic_failed_jobs WHERE id = ?',
            [$id],
        );
        $this->database->execute('DELETE FROM aic_failed_jobs WHERE id = ?', [$id]);
        $this->database->commit();

        return $queued === 1;
    }

    /**
     * @param int $id A failed job's id.
     *
     * @throws QueryException When the record cannot be changed.
     *
     * @return bool Whether it was in the failed record and is gone now (#262).
     */
    public function discard(int $id): bool
    {
        return $this->database->execute('DELETE FROM aic_failed_jobs WHERE id = ?', [$id]) === 1;
    }
}
