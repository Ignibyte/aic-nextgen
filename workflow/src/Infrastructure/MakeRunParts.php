<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Make\MakeRecorder;
use AIC\Workflow\Application\Make\MakeScope;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Infrastructure\Guard\GuardKernel;
use AIC\Workflow\Infrastructure\Guard\GuardLedgerFile;
use AIC\Workflow\Infrastructure\Guard\GuardParts;

use function is_file;

/**
 * The make commands' side of the open run (#122): the guard's edit wall on
 * each target, asked only when the project has `config/workflow.php`, with
 * its ledger, and the recorder of `code.made`.
 */
final readonly class MakeRunParts
{
    /**
     * @param string $root    The project's root.
     * @param string $home    The user's home, for the guard's protected paths; empty when unknown.
     * @param string $package The workflow package's directory, for the same.
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
    ) {
    }

    /**
     * @return MakeScope The guard's word on each target.
     */
    public function scope(): MakeScope
    {
        return new MakeScope(
            $this->root,
            (new GuardParts($this->root, $this->home, $this->package))->editWall(),
            new GuardLedgerFile($this->root),
            new UtcTime(new SystemClock()),
            $this->scene(...),
        );
    }

    /**
     * @return MakeRecorder Appends `code.made` while a run is open.
     */
    public function recorder(): MakeRecorder
    {
        $eventLogFile = new EventLogFile($this->root, new UtcTime(new SystemClock()), new LocalHost($this->root));

        return new MakeRecorder(new RunStateFile($this->root), $eventLogFile);
    }

    /**
     * @return GuardScene|null The guard's settings and run, read when a make asks; null without `config/workflow.php`.
     */
    private function scene(): ?GuardScene
    {
        return is_file($this->root . '/config/workflow.php')
            ? (new GuardKernel($this->root, $this->home, $this->package))->scene()
            : null;
    }
}
