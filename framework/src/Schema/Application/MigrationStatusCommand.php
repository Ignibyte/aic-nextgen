<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function str_pad;

/**
 * `aic migrate:status`: each migration with its state (applied, pending, or
 * changed since it was applied), in order.
 */
final readonly class MigrationStatusCommand implements Command
{
    /**
     * @param Migrator $migrator Reads the ledger.
     */
    public function __construct(
        private Migrator $migrator,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse The states.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['migrate:status takes no arguments.'], 2);
        }

        try {
            $states = $this->migrator->status();
        } catch (QueryException $queryException) {
            return new CommandResult(['migrate:status: ' . $queryException->getMessage()], 1);
        }

        $lines = [];

        foreach ($states as $version => $state) {
            $lines[] = str_pad($state, 9) . $version;
        }

        return new CommandResult($lines, 0);
    }
}
