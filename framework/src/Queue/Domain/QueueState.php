<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

/**
 * Where a job in the queue stands (#262), by the rule `PostgresQueue::reserve()`
 * takes jobs by: a reservation under ten minutes old is a worker on it.
 */
enum QueueState: string
{
    /** Not tried yet, due at its time. */
    case Waiting = 'waiting';

    /** A worker holds it now. */
    case Running = 'running';

    /** Tried and released to try again, due at its time. */
    case Retrying = 'retrying';
}
