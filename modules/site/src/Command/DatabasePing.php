<?php

declare(strict_types=1);

namespace App\Site\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Application\DatabaseStatus;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

/**
 * `aic db:ping [setting]`: whether the database answers, with one of the
 * server's settings, `server_version` by default. Exit code 0 when it answers,
 * 1 when the query fails (an unknown setting fails it too).
 */
final readonly class DatabasePing implements Command
{
    /**
     * @param DatabaseStatus $databaseStatus The database's own state.
     */
    public function __construct(
        private DatabaseStatus $databaseStatus,
    ) {
    }

    /**
     * @param CommandInput $commandInput The setting's name, first.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse The answer.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $setting = $commandInput->argument(0, 'server_version');

        try {
            $value = $this->databaseStatus->setting($setting);
        } catch (QueryException $queryException) {
            return new CommandResult(['db:ping: ' . $queryException->getMessage()], 1);
        }

        return new CommandResult(['Database reachable: ' . $setting . ' = ' . $value], 0);
    }
}
