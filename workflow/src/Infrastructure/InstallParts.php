<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\InstallContext;
use AIC\Workflow\Application\InstallHost;
use AIC\Workflow\Application\InstallPreflight;
use AIC\Workflow\Application\ManagedRegion;
use AIC\Workflow\Application\PackFileStep;
use AIC\Workflow\Application\PackInstaller;
use AIC\Workflow\Application\PackPlanner;
use AIC\Workflow\Application\PackRegionStep;
use AIC\Workflow\Domain\WorkflowClient;
use Override;

/**
 * `workflow:init`'s composition (#105): the installer over the project at
 * the root and the pack in this package, built when the command runs. It
 * reads no `config/workflow.php` until there is one.
 */
final readonly class InstallParts implements InstallContext
{
    /**
     * This package's root, whose `pack/` init installs.
     */
    private const string PACKAGE = __DIR__ . '/../..';

    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param WorkflowClient $workflowClient The selected client.
     *
     * @return PackInstaller The installer.
     */
    #[Override]
    public function packInstaller(WorkflowClient $workflowClient = WorkflowClient::Claude): PackInstaller
    {
        $installFiles = new InstallFiles($this->root, self::PACKAGE, $this->composerManifest()->vendorDir(), $workflowClient);
        $packPlanner = new PackPlanner();
        $managedRegion = new ManagedRegion();
        $installHost = $this->installHost();

        return new PackInstaller(
            $this->preflight($installFiles, $installHost, $managedRegion),
            $installFiles->packageFiles(),
            $installHost,
            new PackFileStep($installFiles->packageFiles(), $installFiles->projectFiles(), $packPlanner),
            new PackRegionStep($installFiles->packageFiles(), $installFiles->projectFiles(), $packPlanner, $managedRegion),
            $installFiles->hookWiring(),
            $installFiles->projectFiles(),
            $installFiles->packLockFile(),
        );
    }

    /**
     * @return InstallHost The project around the pack.
     */
    #[Override]
    public function installHost(): InstallHost
    {
        return new LocalInstallHost($this->root, new LocalHost($this->root), $this->composerManifest());
    }

    /**
     * @return ComposerManifest The project's composer.json.
     */
    private function composerManifest(): ComposerManifest
    {
        return new ComposerManifest($this->root, self::PACKAGE);
    }

    /**
     * @param InstallFiles  $installFiles  The selected client files and wiring.
     * @param InstallHost   $installHost   The project host.
     * @param ManagedRegion $managedRegion The shared region parser.
     *
     * @return InstallPreflight The complete preflight before any write.
     */
    private function preflight(
        InstallFiles $installFiles,
        InstallHost $installHost,
        ManagedRegion $managedRegion,
    ): InstallPreflight {
        return new InstallPreflight(
            $installFiles->packageFiles(),
            $installFiles->projectFiles(),
            $installFiles->hookWiring(),
            $installFiles->packLockFile(),
            $installHost,
            $managedRegion,
            $installFiles->lockPath(),
        );
    }
}
