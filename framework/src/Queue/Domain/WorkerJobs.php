<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;

/**
 * An interpreted worker's jobs (#336), written by `composer run build` into
 * `workers/<name>/src/Generated/<Name>Jobs.php` from the worker's `src/Job/`.
 * A worker's job takes only what a worker shares with the site: the database,
 * the queue and the app's private files, built from these two.
 */
interface WorkerJobs
{
    /**
     * @param Database $database The app's database.
     * @param string   $root     The app's root, where `var/storage/` is.
     *
     * @return array<string, Job> The worker's jobs, by the name each is queued under.
     */
    public function byName(Database $database, string $root): array;
}
