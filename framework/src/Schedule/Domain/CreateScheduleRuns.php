<?php

declare(strict_types=1);

namespace AIC\Schedule\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The scheduler's ledger (#47), `aic_schedule_runs`: one row per schedule and
 * minute it fired, so no minute fires twice.
 */
final readonly class CreateScheduleRuns implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000005_create_schedule_runs';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_schedule_runs')
            ->string('name', 100)
            ->timestampTz('minute')
            ->primary(['name', 'minute']);
    }
}
