<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

/**
 * Where one queued job stands, by its id (#336), for a page that shows
 * progress. A done job is deleted, so Done is the absence of a record, which
 * an id never queued reads as well.
 */
enum JobState: string
{
    /** Waiting for a worker, a retry included. */
    case Queued = 'queued';

    /** A worker holds it now. */
    case Running = 'running';

    /** In the failed record. */
    case Failed = 'failed';

    /** No record holds it. */
    case Done = 'done';
}
