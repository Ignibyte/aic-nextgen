<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\HookWiring;
use AIC\Workflow\Domain\WorkflowClient;

/** Compose the selected client's package, project, lock and hook wiring. */
final readonly class InstallFiles
{
    /** The selected package files. */
    private PackageFiles $packageFiles;

    /** The project files used by every installation step. */
    private LocalProjectFiles $localProjectFiles;

    /** The selected callback merger. */
    private HookWiring $hookWiring;

    /** The selected committed ownership lock. */
    private PackLockFile $packLockFile;

    /** The selected lock's relative path. */
    private string $lockPath;

    /**
     * @param string         $root           The project root.
     * @param string         $package        The workflow package root.
     * @param string         $vendor         The project's vendor directory.
     * @param WorkflowClient $workflowClient The selected client.
     */
    public function __construct(string $root, string $package, string $vendor, WorkflowClient $workflowClient)
    {
        $packManifest = new PackManifest($workflowClient);
        $this->packageFiles = new PackageFiles($package, $vendor, $packManifest);
        $this->localProjectFiles = new LocalProjectFiles($root);
        $this->lockPath = $packManifest->lock();
        $this->packLockFile = new PackLockFile($this->localProjectFiles, $this->lockPath);
        $this->hookWiring = $workflowClient === WorkflowClient::Claude
            ? new ClaudeSettingsFile($this->localProjectFiles, $this->packageFiles, $packManifest)
            : new HookSettingsFile($this->localProjectFiles, $this->packageFiles, $packManifest);
    }

    /** @return PackageFiles The selected package files. */
    public function packageFiles(): PackageFiles
    {
        return $this->packageFiles;
    }

    /** @return LocalProjectFiles The shared project files. */
    public function projectFiles(): LocalProjectFiles
    {
        return $this->localProjectFiles;
    }

    /** @return HookWiring The selected hook wiring. */
    public function hookWiring(): HookWiring
    {
        return $this->hookWiring;
    }

    /** @return PackLockFile The selected ownership lock. */
    public function packLockFile(): PackLockFile
    {
        return $this->packLockFile;
    }

    /** @return string The selected lock path. */
    public function lockPath(): string
    {
        return $this->lockPath;
    }
}
