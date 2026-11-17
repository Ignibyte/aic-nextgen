<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `scaffold_tasks_scaffold_events` pivot between `scaffold_tasks` and `scaffold_events`, made by bin/aic
 * make:relation (#306): each row links one `scaffold_tasks` row to one `scaffold_events`
 * row, once, with fields of its own; deleting either row deletes the link.
 */
final readonly class CreateScaffoldTasksScaffoldEventsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000001_create_scaffold_tasks_scaffold_events_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('scaffold_tasks_scaffold_events')
            ->identity('id')
            ->bigInteger('scaffold_task_id')
            ->column('scaffold_event_id', $schema->types()->uuid())
            ->text('note')
            ->primary(['id'])
            ->unique(['scaffold_task_id', 'scaffold_event_id'])
            ->foreignKey('scaffold_task_id', 'scaffold_tasks', 'id', OnDelete::Cascade)
            ->foreignKey('scaffold_event_id', 'scaffold_events', 'id', OnDelete::Cascade);
    }
}
