<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The workflow's parts as the commands reach them (#100), built at the first
 * call, so a project without `config/workflow.php` is refused there.
 */
interface WorkflowContext
{
    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunEngine The engine.
     */
    public function engine(): RunEngine;

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunReset The operator's reset.
     */
    public function runReset(): RunReset;

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return RunStore The run's store.
     */
    public function runStore(): RunStore;

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SeekerFiling Files a seeker's ledger (#103).
     */
    public function seekerFiling(): SeekerFiling;

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SeekerBrief The seeker's scope (#103).
     */
    public function seekerBrief(): SeekerBrief;

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return QuestionHold The questions a run waits on (#117).
     */
    public function questionHold(): QuestionHold;
}
