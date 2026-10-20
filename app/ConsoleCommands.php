<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Schema\Application\MigrateCommand;
use AIC\Schema\Application\MigrationStatusCommand;
use AIC\Schema\Application\Migrator;
use AIC\Schema\Application\PostgresDdl;
use AIC\Session\Domain\CreateSessionsTable;
use App\Console\DatabasePing;
use App\Console\Greet;
use App\Migration\CreateNotesTable;

/**
 * The app's commands by name: the console's composition root, as AppHandlers
 * is the worker's. bin/aic hands the map to the console. The app's migrations
 * are listed here, in the order they apply.
 */
final readonly class ConsoleCommands
{
    /**
     * @param Database   $database   The database.
     * @param AppPlugins $appPlugins The app's plugins.
     */
    public function __construct(
        private Database $database,
        private AppPlugins $appPlugins,
    ) {
    }

    /**
     * @return array<string, Command> The commands by name.
     */
    public function byName(): array
    {
        $migrator = new Migrator($this->database, new PostgresDdl(), [new CreateNotesTable(), new CreateSessionsTable()]);

        return [
            'db:ping' => new DatabasePing($this->database),
            'greet' => new Greet($this->appPlugins->greeters()),
            'migrate' => new MigrateCommand($migrator),
            'migrate:status' => new MigrationStatusCommand($migrator),
        ];
    }
}
