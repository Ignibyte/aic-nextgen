<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The queue's tables (#46): `aic_jobs`, the jobs waiting or running, and
 * `aic_failed_jobs`, the ones that kept failing. An app that queues jobs
 * registers this migration with its own.
 */
final readonly class CreateJobTables implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000004_create_job_tables';
    }

    /**
     * @param Schema $schema The schema to describe them on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_jobs')
            ->identity('id')
            ->string('job', 200)
            ->text('payload')
            ->integer('attempts')
            ->timestampTz('available_at')
            ->timestampTz('reserved_at')
            ->nullable()
            ->primary(['id']);
        $schema->create('aic_failed_jobs')
            ->identity('id')
            ->string('job', 200)
            ->text('payload')
            ->integer('attempts')
            ->text('reason')
            ->timestampTz('failed_at')
            ->primary(['id']);
    }
}
