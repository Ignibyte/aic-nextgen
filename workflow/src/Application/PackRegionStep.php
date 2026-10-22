<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\InstallReport;
use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackRole;

/**
 * The pack's regions (#105): the `AGENTS.md` block and the `CLAUDE.md`
 * import, each held by the lock like an owned file, over the lines between
 * its markers only. A file that already holds the region's lines outside
 * the markers, as a CLAUDE.md that imports `@AGENTS.md`, is left alone.
 */
final readonly class PackRegionStep
{
    /**
     * Why a file that already holds the region's lines is left alone.
     */
    private const string HELD = 'the file holds it outside the markers already';

    /**
     * @param PackSource    $packSource    The shipped bytes.
     * @param ProjectFiles  $projectFiles  The project's files.
     * @param PackPlanner   $packPlanner   The lock's decision table.
     * @param ManagedRegion $managedRegion The markers.
     */
    public function __construct(
        private PackSource $packSource,
        private ProjectFiles $projectFiles,
        private PackPlanner $packPlanner,
        private ManagedRegion $managedRegion,
    ) {
    }

    /**
     * @param list<PackFile> $packFiles     The pack's rows.
     * @param InstallForce   $installForce  What the operator forces.
     * @param InstallReport  $installReport The report so far.
     *
     * @return InstallReport The report with a line per region, and the lock after them.
     */
    public function apply(array $packFiles, InstallForce $installForce, InstallReport $installReport): InstallReport
    {
        foreach ($packFiles as $packFile) {
            if ($packFile->packRole !== PackRole::Region) {
                continue;
            }

            $installReport = $this->region($packFile, $installForce, $installReport);
        }

        return $installReport;
    }

    /**
     * @param PackFile      $packFile      A region's row.
     * @param InstallForce  $installForce  What the operator forces.
     * @param InstallReport $installReport The report so far.
     *
     * @return InstallReport The report after it.
     */
    private function region(PackFile $packFile, InstallForce $installForce, InstallReport $installReport): InstallReport
    {
        $shipped = (string) $this->packSource->bytes($packFile);
        $text = $this->projectFiles->read($packFile->destination);

        if ($this->managedRegion->heldOutside($text, $shipped)) {
            return $installReport->with(new InstallLine(InstallAction::Kept, $packFile->lockKey(), self::HELD));
        }

        $packLock = $installReport->packLock;
        $region = $this->managedRegion->region($text);
        $installLine = $this->packPlanner->owned($packFile->lockKey(), $region, $shipped, $packLock, $installForce->coversFile($packFile));

        if ($installLine->installAction === InstallAction::Wrote) {
            $this->projectFiles->write($packFile->destination, $this->managedRegion->upsert($text, $shipped));
        }

        return $installReport->with($installLine)->locked($this->packPlanner->lockAfter($installLine, $shipped, $packLock));
    }
}
