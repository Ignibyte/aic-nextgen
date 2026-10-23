<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use InvalidArgumentException;
use Override;

/** Report actual native callback readiness before a fresh Codex session starts. */
final readonly class WorkflowCodexCheckCommand implements Command
{
    /** @param CodexReadiness $codexReadiness The read-only native readiness check. */
    public function __construct(private CodexReadiness $codexReadiness)
    {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When an invalid response exit is constructed.
     *
     * @return CommandResponse Actual readiness, or the reasons launch is held.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if ($commandInput->arguments() !== []) {
            return new CommandResult(['workflow:codex-check takes no arguments.'], 2);
        }

        $problems = $this->codexReadiness->problems();

        return $problems === []
            ? new CommandResult(['Codex workflow callbacks match the package and are enabled and trusted.'], 0)
            : new CommandResult($problems, 2);
    }
}
