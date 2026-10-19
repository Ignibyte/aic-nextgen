<?php

declare(strict_types=1);

namespace App\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Override;

/**
 * `aic db:ping [setting]`: whether the database answers, with one of the
 * server's settings, `server_version` by default. Exit code 0 when it answers,
 * 1 when the query fails (an unknown setting fails it too).
 */
final readonly class DatabasePing implements Command
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param CommandInput $commandInput The setting's name, first.
     *
     * @return CommandResponse The answer.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $setting = $commandInput->argument(0, 'server_version');

        try {
            $value = $this->database->fetchOne('SELECT current_setting(?) AS value', [$setting])->string('value');
        } catch (QueryException $queryException) {
            return new CommandResult(['db:ping: ' . $queryException->getMessage()], 1);
        }

        return new CommandResult(['Database reachable: ' . $setting . ' = ' . $value], 0);
    }
}
