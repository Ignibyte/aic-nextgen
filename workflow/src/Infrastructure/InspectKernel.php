<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Console\CommandListCommand;
use AIC\Workflow\Application\Console\JobListCommand;
use AIC\Workflow\Application\Console\KindListCommand;
use AIC\Workflow\Application\Console\ModuleListCommand;
use AIC\Workflow\Application\Console\ModuleShowCommand;
use AIC\Workflow\Application\Console\RouteListCommand;
use AIC\Workflow\Application\Console\ScheduleListCommand;

/**
 * The inspect commands (#109), dev-only like the rest of the workflow:
 * `route:list`, `command:list`, `job:list`, `schedule:list`, `module:list`,
 * `module:show` and `kind:list`, each answering from `build/surface.json`
 * (and `module:show` from the code index too) and recording its lookup while
 * a run is open; and `code:coverage` (#232), the report over the code index.
 * `CodeKernel` merges them, so `WorkflowKernel` stays under PHPMD's coupling
 * cap.
 */
final readonly class InspectKernel
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return array<string, Command> The eight commands, by name.
     */
    public function commands(): array
    {
        $inspectRun = (new InspectParts($this->root))->inspectRun();
        $codeParts = new CodeParts($this->root);

        return [
            'code:coverage' => (new CoverageKernel($this->root))->command($codeParts->codeIndex()),
            'command:list' => new CommandListCommand($inspectRun, fn (): array => (new WorkflowKernel($this->root))->commands()),
            'job:list' => new JobListCommand($inspectRun),
            'kind:list' => new KindListCommand($inspectRun),
            'module:list' => new ModuleListCommand($inspectRun),
            'module:show' => new ModuleShowCommand($inspectRun, $codeParts->codeIndex(), $codeParts->codeLookup()),
            'route:list' => new RouteListCommand($inspectRun),
            'schedule:list' => new ScheduleListCommand($inspectRun),
        ];
    }
}
