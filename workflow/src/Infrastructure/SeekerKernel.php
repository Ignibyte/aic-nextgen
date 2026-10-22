<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowContext;
use AIC\Workflow\Application\WorkflowSeekerBriefCommand;
use AIC\Workflow\Application\WorkflowSeekerReportCommand;

/**
 * The seeker's commands (#103), kept apart so `WorkflowKernel` stays under
 * PHPMD's coupling cap: `workflow:seeker-brief` and `workflow:seeker-report`,
 * each reaching the engine through the workflow's context.
 */
final readonly class SeekerKernel
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @return array<string, Command> The two commands, by name.
     */
    public function commands(): array
    {
        return [
            'workflow:seeker-brief' => new WorkflowSeekerBriefCommand($this->workflowContext),
            'workflow:seeker-report' => new WorkflowSeekerReportCommand($this->workflowContext),
        ];
    }
}
