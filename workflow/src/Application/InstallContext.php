<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * `workflow:init`'s parts (#105), built when the command runs, so listing
 * `bin/aic`'s commands reads nothing.
 */
interface InstallContext
{
    /**
     * @return PackInstaller The installer, for this project.
     */
    public function packInstaller(): PackInstaller;

    /**
     * @return InstallHost The project around the pack.
     */
    public function installHost(): InstallHost;
}
