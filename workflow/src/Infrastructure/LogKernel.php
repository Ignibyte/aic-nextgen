<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowLogsCommand;

/** Composes the read-only gate log command without exposing the engine's evidence writers. */
final readonly class LogKernel
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /** @return array<string, Command> The supported diagnostic command. */
    public function commands(): array
    {
        return [
            'workflow:logs' => new WorkflowLogsCommand(new RunStateFile($this->root), new GateLogFile(new ReadOnlyEvidence($this->root))),
        ];
    }
}
