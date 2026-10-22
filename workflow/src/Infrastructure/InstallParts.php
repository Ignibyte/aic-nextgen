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
     * @return PackInstaller The installer.
     */
    #[Override]
    public function packInstaller(): PackInstaller
    {
        $packageFiles = new PackageFiles(self::PACKAGE, $this->composerManifest()->vendorDir());
        $localProjectFiles = new LocalProjectFiles($this->root);
        $packPlanner = new PackPlanner();
        $managedRegion = new ManagedRegion();
        $claudeSettingsFile = new ClaudeSettingsFile($localProjectFiles, $packageFiles);
        $packLockFile = new PackLockFile($localProjectFiles);
        $installHost = $this->installHost();

        return new PackInstaller(
            new InstallPreflight($packageFiles, $localProjectFiles, $claudeSettingsFile, $packLockFile, $installHost, $managedRegion),
            $packageFiles,
            $installHost,
            new PackFileStep($packageFiles, $localProjectFiles, $packPlanner),
            new PackRegionStep($packageFiles, $localProjectFiles, $packPlanner, $managedRegion),
            $claudeSettingsFile,
            $localProjectFiles,
            $packLockFile,
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
}
