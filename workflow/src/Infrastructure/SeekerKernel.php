<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowContext;
use AIC\Workflow\Application\WorkflowSeekerBriefCommand;
use AIC\Workflow\Application\WorkflowSeekerReportCommand;
use AIC\Workflow\Infrastructure\Probe\ProbeParts;

/**
 * The seeker's commands (#103), kept apart so `WorkflowKernel` stays under
 * PHPMD's coupling cap: `workflow:seeker-brief` and `workflow:seeker-report`,
 * each reaching the engine through the workflow's context, and
 * `workflow:probe` (#119), whose apps the seeker probe's builder makes.
 */
final readonly class SeekerKernel
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     * @param string          $root            The project's root, for the probe sweep.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
        private string $root,
    ) {
    }

    /**
     * @return array<string, Command> The three commands, by name.
     */
    public function commands(): array
    {
        return [
            'workflow:seeker-brief' => new WorkflowSeekerBriefCommand($this->workflowContext),
            'workflow:seeker-report' => new WorkflowSeekerReportCommand($this->workflowContext),
            ...(new ProbeParts($this->root))->commands(),
        ];
    }
}
