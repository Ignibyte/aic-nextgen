<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\QuestionHold;
use AIC\Workflow\Application\RunEngine;
use AIC\Workflow\Application\RunReset;
use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Application\SeekerBrief;
use AIC\Workflow\Application\SeekerFiling;
use AIC\Workflow\Application\WorkflowContext;
use AIC\Workflow\Application\WorkflowRunCommand;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * The workflow's entry (#100): its commands for `bin/aic`, and its parts,
 * built at the first command from the project's `config/workflow.php`, so a
 * project without the file is refused there and nothing else changes.
 */
final readonly class WorkflowKernel implements WorkflowContext
{
    /**
     * @param string       $root         The project's root: the directory `bin/aic` runs in.
     * @param SettingsFile $settingsFile Reads `config/workflow.php`.
     */
    public function __construct(
        private string $root,
        private SettingsFile $settingsFile = new SettingsFile(),
    ) {
    }

    /**
     * @return array<string, Command> The workflow's commands, by name, the installer (#105) and the code index's (#106)
     *                                among them.
     */
    public function commands(): array
    {
        return [
            ...(new HoldKernel($this, $this->root))->commands(),
            ...(new InstallKernel($this->root))->commands(),
            ...(new LogKernel($this->root))->commands(),
            ...(new SeekerKernel($this, $this->root))->commands(),
            ...(new CodeKernel($this->root))->commands(),
            'workflow:run' => new WorkflowRunCommand($this),
        ];
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunEngine The engine.
     */
    #[Override]
    public function engine(): RunEngine
    {
        return $this->parts()->engine();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunReset The operator's reset.
     */
    #[Override]
    public function runReset(): RunReset
    {
        return $this->parts()->runReset();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunStore The run's store.
     */
    #[Override]
    public function runStore(): RunStore
    {
        return $this->parts()->runStore();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SeekerFiling Files a seeker's ledger.
     */
    #[Override]
    public function seekerFiling(): SeekerFiling
    {
        return $this->parts()->seekerParts()->seekerFiling();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SeekerBrief The seeker's scope.
     */
    #[Override]
    public function seekerBrief(): SeekerBrief
    {
        return $this->parts()->seekerParts()->seekerBrief();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return QuestionHold The questions a run waits on (#117).
     */
    #[Override]
    public function questionHold(): QuestionHold
    {
        return $this->parts()->holdParts()->questionHold();
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return WorkflowParts The parts, for this project.
     */
    private function parts(): WorkflowParts
    {
        return new WorkflowParts($this->root, $this->settingsFile->read($this->root));
    }
}
