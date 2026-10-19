<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use Override;

/**
 * `aic migrate`: applies the pending migrations, in order, and names each. A
 * failed migration is rolled back and stops the run with exit code 1.
 */
final readonly class MigrateCommand implements Command
{
    /**
     * @param Migrator $migrator Runs the migrations.
     */
    public function __construct(
        private Migrator $migrator,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @return CommandResponse What was applied.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['migrate takes no arguments.'], 2);
        }

        try {
            $versions = $this->migrator->migrate();
        } catch (QueryException $queryException) {
            return new CommandResult(['migrate: ' . $queryException->getMessage()], 1);
        }

        $lines = ['Nothing to migrate.'];

        if ($versions !== []) {
            $lines = [];
        }

        foreach ($versions as $version) {
            $lines[] = 'Applied ' . $version;
        }

        return new CommandResult($lines, 0);
    }
}
