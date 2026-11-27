<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

use AIC\Schema\Contracts\Migration;

/**
 * The queue's migrations, in the order they apply: its tables (#46), then
 * the named queues and the ids a failed job keeps (#336).
 */
final readonly class QueueMigrations
{
    /**
     * @return list<Migration> Each migration, in order.
     */
    public function all(): array
    {
        return [new CreateJobTables(), new AddJobQueues()];
    }
}
