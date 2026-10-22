<?php

declare(strict_types=1);

namespace App\Scaffold\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use Override;

/**
 * `aic scaffold:hello`, made by bin/aic make:command (#122): one line.
 */
final readonly class ScaffoldHello implements Command
{
    /**
     * @param CommandInput $commandInput The arguments.
     *
     * @return CommandResponse Its line, exit 0.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return new CommandResult(['scaffold:hello'], 0);
    }
}
