<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `scaffold_tasks` table, made by bin/aic make:entity (#123) for ScaffoldTask.
 */
final readonly class CreateScaffoldTasksTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_25_000000_create_scaffold_tasks_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('scaffold_tasks')
            ->identity('id')
            ->text('title')
            ->date('due_on')
            ->boolean('done')
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
