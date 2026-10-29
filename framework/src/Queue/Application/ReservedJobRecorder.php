<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\JobRecorder;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Domain\ReservedJob;
use Override;

use function max;

/**
 * Records one reserved job's result on the queue (#46): done deletes it; a
 * retry waits at least `10 s × 2^(attempt-1)`, and the third attempt that
 * does not succeed fails it; a failure moves it to the failed record.
 */
final readonly class ReservedJobRecorder implements JobRecorder
{
    /**
     * The first backoff, in seconds; each later one doubles.
     */
    private const int BACKOFF = 10;

    /**
     * @param PostgresQueue $postgresQueue The queue.
     * @param ReservedJob   $reservedJob   The job that ran.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
        private ReservedJob $reservedJob,
    ) {
    }

    /**
     * @throws QueryException When the queue cannot be changed.
     */
    #[Override]
    public function done(): void
    {
        $this->postgresQueue->delete($this->reservedJob->id);
    }

    /**
     * @param int $delaySeconds The least time to wait before the next attempt.
     * @param int $attempts     How many attempts the job gets in all (#208).
     *
     * @throws QueryException When the queue cannot be changed.
     */
    #[Override]
    public function retry(int $delaySeconds, int $attempts): void
    {
        if ($this->reservedJob->attempts >= $attempts) {
            $this->fail('Gave up after ' . $this->reservedJob->attempts . ' attempts.');

            return;
        }

        $backoff = self::backoff($this->reservedJob->attempts);
        $this->postgresQueue->release($this->reservedJob->id, max($delaySeconds, $backoff));
    }

    /**
     * @param string $reason Why it cannot succeed.
     *
     * @throws QueryException When the queue cannot be changed.
     */
    #[Override]
    public function fail(string $reason): void
    {
        $this->postgresQueue->bury($this->reservedJob, $reason);
    }

    /**
     * @param int $attempt The attempt that will be retried.
     *
     * @return int The exponential delay in whole seconds.
     */
    private static function backoff(int $attempt): int
    {
        return (int) ((float) self::BACKOFF * (2.0 ** (float) ($attempt - 1)));
    }
}
