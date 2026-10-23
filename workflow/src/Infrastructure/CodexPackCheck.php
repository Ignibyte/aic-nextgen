<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ManagedRegion;
use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackLock;
use AIC\Workflow\Domain\PackRole;
use AIC\Workflow\Domain\WorkflowClient;
use AIC\Workflow\Exception\WorkflowRefusal;

use function hash;
use function is_file;
use function str_starts_with;

/** Check installed native controls against their package and committed ownership before live callback discovery. */
final readonly class CodexPackCheck
{
    /**
     * @param string $root   The actual app root.
     * @param string $vendor The actual vendor directory.
     */
    public function __construct(private string $root, private string $vendor)
    {
    }

    /** @throws WorkflowRefusal When native controls are missing, linked or differ from the owned package. */
    public function check(): void
    {
        $localProjectFiles = new LocalProjectFiles($this->root);

        foreach (['config/workflow.php', '.codex/hooks.json', '.codex/agents/aic-seeker.toml', '.codex/aic-workflow.lock'] as $path) {
            if ($localProjectFiles->obstacle($path) !== '' || $localProjectFiles->read($path) === null) {
                throw new WorkflowRefusal('Run workflow:init --client=codex before native launch.');
            }
        }

        if (!is_file($this->root . '/' . $this->vendor . '/aic/workflow/bin/aic-codex-guard.sh')) {
            throw new WorkflowRefusal('The installed native guard is missing. Restore the package.');
        }

        $this->owned($localProjectFiles);
    }

    /**
     * @param LocalProjectFiles $localProjectFiles The app controls.
     *
     * @throws WorkflowRefusal When an owned file or lock differs.
     */
    private function owned(LocalProjectFiles $localProjectFiles): void
    {
        $packManifest = new PackManifest(WorkflowClient::Codex);
        $packLock = (new PackLockFile($localProjectFiles, $packManifest->lock()))->load();
        $packageFiles = new PackageFiles(__DIR__ . '/../..', $this->vendor, $packManifest);

        foreach ($packageFiles->files('') as $packFile) {
            if ($packFile->packRole === PackRole::WriteOnce || str_starts_with($packFile->source, 'tickets/')) {
                continue;
            }

            $this->file($packFile, $packageFiles, $localProjectFiles, $packLock);
        }
    }

    /**
     * @param PackFile          $packFile          The owned role, skill or managed instructions.
     * @param PackageFiles      $packageFiles      The selected native package.
     * @param LocalProjectFiles $localProjectFiles The actual app.
     * @param PackLock          $packLock          The committed ownership.
     *
     * @throws WorkflowRefusal When the actual file or ownership no longer matches.
     */
    private function file(
        PackFile $packFile,
        PackageFiles $packageFiles,
        LocalProjectFiles $localProjectFiles,
        PackLock $packLock,
    ): void {
        $text = $localProjectFiles->read($packFile->destination);
        $actual = $packFile->packRole === PackRole::Region
            ? (new ManagedRegion())->region($text)
            : $text;
        $shipped = $packageFiles->bytes($packFile);
        $obstacle = $localProjectFiles->obstacle($packFile->destination);

        if ($obstacle !== '' || !$this->matches($actual, $shipped, $packLock->hash($packFile->lockKey()))) {
            throw new WorkflowRefusal(
                $packFile->destination . ' differs from the owned native pack. Review it and rerun workflow:init.',
            );
        }
    }

    /**
     * @param string|null $actual  The actual control bytes.
     * @param string|null $shipped The installed definition.
     * @param string|null $locked  The recorded owned hash.
     *
     * @return bool Whether all three describe the same control.
     */
    private function matches(?string $actual, ?string $shipped, ?string $locked): bool
    {
        return $shipped !== null && $actual === $shipped && $locked === 'sha256:' . hash('sha256', $shipped);
    }
}
