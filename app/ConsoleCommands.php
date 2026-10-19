<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use App\Console\DatabasePing;
use App\Console\Greet;

/**
 * The app's commands by name: the console's composition root, as AppHandlers
 * is the worker's. bin/aic hands the map to the console.
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
        return [
            'db:ping' => new DatabasePing($this->database),
            'greet' => new Greet($this->appPlugins->greeters()),
        ];
    }
}
