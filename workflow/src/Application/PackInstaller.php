<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\InstallReport;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * `workflow:init`'s work (#105): the preflight, then in order the pack's
 * files, its regions, the guard's wiring, the ignore line, and last the
 * lock. A path whose bytes would not change is not written.
 */
final readonly class PackInstaller
{
    /**
     * @param InstallPreflight $installPreflight Every refusal, before any write.
     * @param PackSource       $packSource       The pack's rows.
     * @param InstallHost      $installHost      The ticket directory.
     * @param PackFileStep     $packFileStep     Whole files.
     * @param PackRegionStep   $packRegionStep   Regions.
     * @param HookWiring       $hookWiring       settings.json.
     * @param ProjectFiles     $projectFiles     `.gitignore`.
     * @param PackLockStore    $packLockStore    The lock.
     * @param IgnoreLine       $ignoreLine       The `/var/` line.
     */
    public function __construct(
        private InstallPreflight $installPreflight,
        private PackSource $packSource,
        private InstallHost $installHost,
        private PackFileStep $packFileStep,
        private PackRegionStep $packRegionStep,
        private HookWiring $hookWiring,
        private ProjectFiles $projectFiles,
        private PackLockStore $packLockStore,
        private IgnoreLine $ignoreLine = new IgnoreLine(),
    ) {
    }

    /**
     * @param InstallForce $installForce What the operator forces.
     *
     * @throws WorkflowRefusal When the preflight refuses (nothing written), or a write fails.
     *
     * @return InstallReport A line per path.
     */
    public function install(InstallForce $installForce): InstallReport
    {
        $tickets = $this->installHost->tickets();
        $this->installPreflight->tickets($tickets);
        $packFiles = $this->packSource->files($tickets);
        $installReport = new InstallReport($this->installPreflight->check($packFiles, $installForce));
        $installReport = $this->packFileStep->apply($packFiles, $installForce, $installReport);
        $installReport = $this->packRegionStep->apply($packFiles, $installForce, $installReport);

        foreach ($this->hookWiring->wire() as $installLine) {
            $installReport = $installReport->with($installLine);
        }

        return $installReport->with($this->ignore())->with($this->packLockStore->save($installReport->packLock));
    }

    /**
     * @throws WorkflowRefusal When `.gitignore` cannot be written.
     *
     * @return InstallLine `.gitignore`'s line: current when it ignores `var/` already.
     */
    private function ignore(): InstallLine
    {
        $text = $this->projectFiles->read('.gitignore');

        if ($this->ignoreLine->present($text)) {
            return new InstallLine(InstallAction::Current, '.gitignore');
        }

        $this->projectFiles->write('.gitignore', $this->ignoreLine->add($text));

        return new InstallLine(InstallAction::Wrote, '.gitignore', 'ignores ' . IgnoreLine::LINE);
    }
}
