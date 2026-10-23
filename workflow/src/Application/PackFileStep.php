<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\InstallReport;
use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackRole;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function in_array;
use function str_contains;

/**
 * The pack's whole files (#105): each owned file written, upgraded or kept
 * by the lock, each write-once file written only when absent, and each file
 * the lock lists that the pack no longer ships retired.
 */
final readonly class PackFileStep
{
    /**
     * Why a write-once file that is there is kept.
     */
    private const string ONCE = "the operator's; init writes it once";

    /**
     * @param PackSource   $packSource   The shipped bytes.
     * @param ProjectFiles $projectFiles The project's files.
     * @param PackPlanner  $packPlanner  The lock's decision table.
     */
    public function __construct(
        private PackSource $packSource,
        private ProjectFiles $projectFiles,
        private PackPlanner $packPlanner,
    ) {
    }

    /**
     * @param list<PackFile> $packFiles     The pack's rows.
     * @param InstallForce   $installForce  What the operator forces.
     * @param InstallReport  $installReport The report so far.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return InstallReport The report with a line per file, and the lock after them.
     */
    public function apply(array $packFiles, InstallForce $installForce, InstallReport $installReport): InstallReport
    {
        foreach ($packFiles as $packFile) {
            $installReport = match ($packFile->packRole) {
                PackRole::Owned => $this->owned($packFile, $installForce, $installReport),
                PackRole::WriteOnce => $this->writeOnce($packFile, $installReport),
                PackRole::Region => $installReport,
            };
        }

        $shipped = array_map(static fn (PackFile $packFile): string => $packFile->lockKey(), $packFiles);

        foreach ($installReport->packLock->paths() as $path) {
            if (in_array($path, $shipped, true) || str_contains($path, '#')) {
                continue;
            }

            $installReport = $this->retired($path, $installForce, $installReport);
        }

        return $installReport;
    }

    /**
     * @param PackFile      $packFile      An owned row.
     * @param InstallForce  $installForce  What the operator forces.
     * @param InstallReport $installReport The report so far.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return InstallReport The report after it.
     */
    private function owned(PackFile $packFile, InstallForce $installForce, InstallReport $installReport): InstallReport
    {
        $shipped = (string) $this->packSource->bytes($packFile);
        $disk = $this->projectFiles->read($packFile->destination);
        $packLock = $installReport->packLock;
        $installLine = $this->packPlanner->owned($packFile->destination, $disk, $shipped, $packLock, $installForce->coversFile($packFile));

        if ($installLine->installAction === InstallAction::Wrote) {
            $this->projectFiles->write($packFile->destination, $shipped);
        }

        return $installReport->with($installLine)->locked($this->packPlanner->lockAfter($installLine, $shipped, $packLock));
    }

    /**
     * @param PackFile      $packFile      A write-once row.
     * @param InstallReport $installReport The report so far.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return InstallReport The report after it.
     */
    private function writeOnce(PackFile $packFile, InstallReport $installReport): InstallReport
    {
        if ($this->projectFiles->read($packFile->destination) !== null) {
            return $installReport->with(new InstallLine(InstallAction::Kept, $packFile->destination, self::ONCE));
        }

        $this->projectFiles->write($packFile->destination, (string) $this->packSource->bytes($packFile));

        return $installReport->with(new InstallLine(InstallAction::Wrote, $packFile->destination));
    }

    /**
     * @param string        $path          A path the lock lists and the pack no longer ships.
     * @param InstallForce  $installForce  What the operator forces.
     * @param InstallReport $installReport The report so far.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return InstallReport The report after it.
     */
    private function retired(string $path, InstallForce $installForce, InstallReport $installReport): InstallReport
    {
        $disk = $this->projectFiles->read($path);
        $packLock = $installReport->packLock;
        $installLine = $this->packPlanner->retired($path, $disk, $packLock, $installForce->covers($path));

        if ($installLine->installAction === InstallAction::Removed && $disk !== null) {
            $this->projectFiles->remove($path);
        }

        return $installReport->with($installLine)->locked($this->packPlanner->lockAfter($installLine, '', $packLock));
    }
}
