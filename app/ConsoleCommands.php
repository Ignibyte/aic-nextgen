<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use AIC\Schema\Application\MigrateCommand;
use AIC\Schema\Application\MigrationStatusCommand;
use AIC\Schema\Application\Migrator;
use AIC\Schema\Application\PostgresDdl;
use App\Console\DatabasePing;
use App\Console\DataCommands;
use App\Console\Greet;

use function array_merge;

/**
 * The app's commands by name: the console's composition root, as AppHandlers
 * is the worker's. bin/aic hands the map to the console. The migrations are
 * AppMigrations'; the data commands are DataCommands'.
 */
final readonly class ConsoleCommands
{
    /**
     * @param Database      $database      The database.
     * @param AppPlugins    $appPlugins    The app's plugins.
     * @param MailTransport $mailTransport Sends mail.
     * @param MailConfig    $mailConfig    The sender's address.
     */
    public function __construct(
        private Database $database,
        private AppPlugins $appPlugins,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
    ) {
    }

    /**
     * @return array<string, Command> The commands by name.
     */
    public function byName(): array
    {
        $migrator = new Migrator($this->database, new PostgresDdl(), (new AppMigrations())->all());

        return array_merge([
            'db:ping' => new DatabasePing($this->database),
            'greet' => new Greet($this->appPlugins->greeters()),
            'migrate' => new MigrateCommand($migrator),
            'migrate:status' => new MigrationStatusCommand($migrator),
        ], (new DataCommands($this->database, $this->mailTransport, $this->mailConfig))->byName());
    }
}
