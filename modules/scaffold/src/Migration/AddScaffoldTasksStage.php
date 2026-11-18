<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `stage` state of `scaffold_tasks`, made by bin/aic make:state (#308): a
 * choice of its states, every row, old or new, starting in `open`.
 */
final readonly class AddScaffoldTasksStage implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_000001_add_scaffold_tasks_stage';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When the column is declared wrongly.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $choice = $columnTypes->choice(['open', 'done', 'archived']);
        $term = $schema->expressions()->text('open');
        $schema->alter('scaffold_tasks')->addColumn('stage', $columnTypes->defaulted($choice, $term));
    }
}
