<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/** Checked client and ownership choices for one workflow installation. */
final readonly class InstallRequest
{
    /**
     * @param WorkflowClient $workflowClient The selected client.
     * @param InstallForce   $installForce   The operator's overwrite choices.
     */
    public function __construct(
        public WorkflowClient $workflowClient,
        public InstallForce $installForce,
    ) {
    }
}
