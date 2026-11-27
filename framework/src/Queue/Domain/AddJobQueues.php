<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * Named queues (#336): each job is on a queue, `default` for the site's own
 * and a worker's name for an interpreted worker's, and a failed job keeps the
 * id it had, so a retry puts it back under the same id.
 */
final readonly class AddJobQueues implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_26_000003_add_job_queues';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $columnType = $columnTypes->defaulted($columnTypes->string(200), $schema->expressions()->text('default'));
        $schema->alter('aic_jobs')
            ->addColumn('queue', $columnType)
            ->index(['queue', 'id']);
        $schema->alter('aic_failed_jobs')
            ->addColumn('queue', $columnType)
            ->addColumn('job_id', $columnTypes->bigInteger())
            ->nullable();
    }
}
