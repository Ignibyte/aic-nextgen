<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * What `workflow:amend` reaches the run through (#267), apart from `WorkflowContext` so the kernel
 * that implements that stays under PHPMD's coupling cap.
 */
interface AmendContext
{
    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SpecAmend The operator's amendment of a frozen spec row.
     */
    public function specAmend(): SpecAmend;
}
