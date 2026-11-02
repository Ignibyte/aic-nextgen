<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The profiler's table (#229): one row per recorded request, its id the
 * `X-AIC-Profile` value, the list columns beside the whole profile as JSON.
 * The writer keeps the newest 500.
 */
final readonly class CreateProfilesTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_02_000001_create_aic_profiles';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_profiles')
            ->string('id', 32)
            ->timestampTz('created_at')
            ->string('method', 16)
            ->text('path')
            ->integer('status')
            ->bigInteger('microseconds')
            ->text('profile')
            ->primary(['id'])
            ->index(['created_at']);
    }
}
