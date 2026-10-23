<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\WorkflowClient;

/**
 * `workflow:init`'s parts (#105), built when the command runs, so listing
 * `bin/aic`'s commands reads nothing.
 */
interface InstallContext
{
    /**
     * @param WorkflowClient $workflowClient The client, Claude by default.
     *
     * @return PackInstaller The installer, for this project.
     */
    public function packInstaller(WorkflowClient $workflowClient = WorkflowClient::Claude): PackInstaller;

    /**
     * @return InstallHost The project around the pack.
     */
    public function installHost(): InstallHost;
}
