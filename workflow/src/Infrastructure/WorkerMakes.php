<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeJobCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeWorkerCommand;
use AIC\Workflow\Application\Make\WorkerJobRecipe;
use AIC\Workflow\Application\ProjectFiles;

/**
 * The makes that read the project's files for an interpreted worker (#335, #336):
 * `make:worker`, and `make:job`, whose `--worker` needs the worker there.
 */
final readonly class WorkerMakes
{
    /**
     * @param ProjectFiles $projectFiles The files the makes read.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
    ) {
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return array<string, Command> Each make, by its command name.
     */
    public function commands(MakeRun $makeRun): array
    {
        return [
            'make:job' => new MakeJobCommand($makeRun, new WorkerJobRecipe($this->projectFiles)),
            'make:worker' => new MakeWorkerCommand($makeRun, $this->projectFiles),
        ];
    }
}
