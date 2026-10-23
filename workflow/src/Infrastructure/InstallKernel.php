<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowCodexCheckCommand;
use AIC\Workflow\Application\WorkflowInitCommand;

/** Compose installation and native readiness without loading project settings while commands are listed. */
final readonly class InstallKernel
{
    /** @param string $root The project root. */
    public function __construct(private string $root)
    {
    }

    /** @return array<string,Command> The installation and readiness commands. */
    public function commands(): array
    {
        return [
            'workflow:codex-check' => new WorkflowCodexCheckCommand(new CodexReadyCheck($this->root)),
            'workflow:init' => new WorkflowInitCommand(new InstallParts($this->root)),
        ];
    }
}
