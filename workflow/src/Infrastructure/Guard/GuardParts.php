<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Application\Guard\EditWall;
use AIC\Workflow\Application\Guard\GuardHook;
use AIC\Workflow\Application\Guard\PhpWall;
use AIC\Workflow\Application\Guard\ProtectedPaths;
use AIC\Workflow\Application\Guard\ShellWall;
use AIC\Workflow\Application\Guard\StopHold;
use AIC\Workflow\Application\Guard\WriteScope;
use AIC\Workflow\Application\SpecFile;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Infrastructure\PackManifest;
use AIC\Workflow\Infrastructure\SystemClock;

/**
 * The guard's composition (#102), the one place that builds it for a
 * project.
 */
final readonly class GuardParts
{
    /**
     * @param string $root    The project's root.
     * @param string $home    The user's home.
     * @param string $package The workflow package.
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
    ) {
    }

    /**
     * @return GuardHook The guard.
     */
    public function guardHook(): GuardHook
    {
        $diskPaths = new DiskPaths($this->home);
        $guardLedgerFile = new GuardLedgerFile($this->root);
        $protectedPaths = $this->protectedPaths($diskPaths);
        $writeScope = new WriteScope($this->root, new SpecFile($this->root), $guardLedgerFile, $diskPaths);
        $phpWall = new PhpWall($this->root, $this->package, $diskPaths);

        return new GuardHook(
            $this->editWall(),
            new ShellWall($protectedPaths, $writeScope, $diskPaths, $phpWall),
            new StopHold($guardLedgerFile),
            $guardLedgerFile,
            new UtcTime(new SystemClock()),
        );
    }

    /**
     * @return EditWall The edit wall alone, which a make asks for each file it would write (#122).
     */
    public function editWall(): EditWall
    {
        $diskPaths = new DiskPaths($this->home);
        $writeScope = new WriteScope($this->root, new SpecFile($this->root), new GuardLedgerFile($this->root), $diskPaths);

        return new EditWall($this->protectedPaths($diskPaths), $writeScope, $diskPaths);
    }

    /**
     * @param DiskPaths $diskPaths Where paths land.
     *
     * @return ProtectedPaths What is never written.
     */
    private function protectedPaths(DiskPaths $diskPaths): ProtectedPaths
    {
        return new ProtectedPaths($this->root, $this->home, $this->package, $diskPaths, (new PackManifest())->guarded());
    }
}
