<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\HookWiring;
use AIC\Workflow\Application\PackSource;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/** Claude wiring retains its Stop cap through the shared typed hook merger. */
final readonly class ClaudeSettingsFile implements HookWiring
{
    /** The environment key that preserves Claude's Stop hold. */
    public const string CAP = 'CLAUDE_CODE_STOP_HOOK_BLOCK_CAP';

    private HookSettingsFile $hookSettingsFile;

    /**
     * @param ProjectFiles $projectFiles The project's files.
     * @param PackSource   $packSource   The shipped callbacks.
     * @param PackManifest $packManifest Their destinations.
     * @param HookMerge    $hookMerge    The owned callback merge.
     */
    public function __construct(
        ProjectFiles $projectFiles,
        PackSource $packSource,
        PackManifest $packManifest = new PackManifest(),
        HookMerge $hookMerge = new HookMerge(),
    ) {
        $this->hookSettingsFile = new HookSettingsFile($projectFiles, $packSource, $packManifest, self::CAP, $hookMerge);
    }

    /** @throws WorkflowRefusal When current or shipped wiring is invalid. */
    #[Override]
    public function check(): void
    {
        $this->hookSettingsFile->check();
    }

    /**
     * @throws WorkflowRefusal When hook wiring cannot be written.
     *
     * @return list<InstallLine> Callback and Stop-cap ownership outcomes.
     */
    #[Override]
    public function wire(): array
    {
        return $this->hookSettingsFile->wire();
    }
}
