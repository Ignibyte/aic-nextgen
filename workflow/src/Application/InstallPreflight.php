<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackLock;
use AIC\Workflow\Domain\PackRole;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function in_array;
use function str_contains;
use function str_starts_with;

/**
 * Everything that refuses `workflow:init`, found before its first write
 * (#105, wider than Droost's directory check): a refused init writes
 * nothing.
 */
final readonly class InstallPreflight
{
    /**
     * Why an agent's `--force` is refused.
     */
    private const string AGENT_FORCE = "--force discards the project's edits, so it is the operator's: "
        . "run it from a terminal, not an agent's shell.";

    /**
     * Why an open run refuses init.
     */
    private const string RUN_OPEN = 'a run is open, and the pack does not change under a run: '
        . 'finish it (or the operator resets it), then run init.';

    /**
     * @param PackSource    $packSource    The shipped bytes.
     * @param ProjectFiles  $projectFiles  The project's files.
     * @param HookWiring    $hookWiring    settings.json.
     * @param PackLockStore $packLockStore The lock.
     * @param InstallHost   $installHost   The project around the pack.
     * @param ManagedRegion $managedRegion The regions' markers.
     * @param string        $lockPath      The selected client's lock.
     */
    public function __construct(
        private PackSource $packSource,
        private ProjectFiles $projectFiles,
        private HookWiring $hookWiring,
        private PackLockStore $packLockStore,
        private InstallHost $installHost,
        private ManagedRegion $managedRegion,
        private string $lockPath = '.claude/aic-workflow.lock',
    ) {
    }

    /**
     * @param list<PackFile> $packFiles    The pack's rows.
     * @param InstallForce   $installForce What the operator forces.
     *
     * @throws WorkflowRefusal At the first thing that keeps init from installing, naming it and the next step.
     *
     * @return PackLock The lock as init finds it.
     */
    public function check(array $packFiles, InstallForce $installForce): PackLock
    {
        $this->force($packFiles, $installForce);
        $this->project();
        $this->hookWiring->check();
        $packLock = $this->packLockStore->load();

        foreach ($packFiles as $packFile) {
            $this->file($packFile);
        }

        $this->obstacle('.gitignore');

        return $packLock;
    }

    /**
     * @param string $tickets The ticket directory the settings name.
     *
     * @throws WorkflowRefusal When it is not a directory inside the project.
     */
    public function tickets(string $tickets): void
    {
        if ($tickets === '' || str_starts_with($tickets, '/') || str_contains('/' . $tickets . '/', '/../')) {
            throw new WorkflowRefusal(
                'the ticket directory "' . $tickets . '" is not a directory inside the project; set `tickets` in config/workflow.php.',
            );
        }
    }

    /**
     * @param list<PackFile> $packFiles    The pack's rows.
     * @param InstallForce   $installForce What the operator forces.
     *
     * @throws WorkflowRefusal When an agent forces, or a forced path is no file init owns.
     */
    private function force(array $packFiles, InstallForce $installForce): void
    {
        if ($installForce->any() && !$this->installHost->operatorTerminal()) {
            throw new WorkflowRefusal(self::AGENT_FORCE);
        }

        foreach ($installForce->paths as $path) {
            if (!$this->owns($packFiles, $path)) {
                throw new WorkflowRefusal('--force=' . $path . ' names no file init owns; ' . $this->lockPath . ' lists them.');
            }
        }
    }

    /**
     * @param list<PackFile> $packFiles The pack's rows.
     * @param string         $path      A path `--force=` names.
     *
     * @return bool Whether it is an owned file or a region, by its path or its lock key.
     */
    private function owns(array $packFiles, string $path): bool
    {
        return array_any(
            $packFiles,
            static fn (PackFile $packFile): bool => $packFile->packRole !== PackRole::WriteOnce
                && in_array($path, [$packFile->destination, $packFile->lockKey()], true),
        );
    }

    /**
     * @throws WorkflowRefusal When a run is open, or composer.json does not declare the workflow for development.
     */
    private function project(): void
    {
        if ($this->installHost->runOpen()) {
            throw new WorkflowRefusal(self::RUN_OPEN);
        }

        $composerProblem = $this->installHost->composerProblem();

        if ($composerProblem !== '') {
            throw new WorkflowRefusal($composerProblem);
        }
    }

    /**
     * @param PackFile $packFile A row.
     *
     * @throws WorkflowRefusal When the package lacks its source, its destination cannot take a regular file, or a region's
     *                         markers are out of order.
     */
    private function file(PackFile $packFile): void
    {
        if ($this->packSource->bytes($packFile) === null) {
            throw new WorkflowRefusal('the package lacks pack/' . $packFile->source . '; reinstall ignibyte/aic-workflow.');
        }

        $this->obstacle($packFile->destination);

        if ($packFile->packRole !== PackRole::Region) {
            return;
        }

        $this->managedRegion->check($packFile->destination, $this->projectFiles->read($packFile->destination));
    }

    /**
     * @param string $path A path init writes.
     *
     * @throws WorkflowRefusal When it cannot take a regular file.
     */
    private function obstacle(string $path): void
    {
        $obstacle = $this->projectFiles->obstacle($path);

        if ($obstacle !== '') {
            throw new WorkflowRefusal($obstacle . '; init writes only regular files, so move it aside first.');
        }
    }
}
