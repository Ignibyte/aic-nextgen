<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Application\Guard\EditWall;
use AIC\Workflow\Application\Guard\GuardHook;
use AIC\Workflow\Application\Guard\OperatorVerbs;
use AIC\Workflow\Application\Guard\ProtectedPaths;
use AIC\Workflow\Application\Guard\ShellWall;
use AIC\Workflow\Application\Guard\ShellWords;
use AIC\Workflow\Application\Guard\ShellWrites;
use AIC\Workflow\Application\Guard\StopHold;
use AIC\Workflow\Application\Guard\WriteScope;
use AIC\Workflow\Application\SpecFile;
use AIC\Workflow\Application\UtcTime;
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
        $protectedPaths = new ProtectedPaths($this->root, $this->home, $this->package, $diskPaths);
        $writeScope = new WriteScope($this->root, new SpecFile($this->root), $guardLedgerFile, $diskPaths);
        $shellWall = new ShellWall(new ShellWords(), new OperatorVerbs(), new ShellWrites(), $protectedPaths, $writeScope, $diskPaths);

        return new GuardHook(
            new EditWall($protectedPaths, $writeScope, $diskPaths),
            $shellWall,
            new StopHold(),
            $guardLedgerFile,
            new UtcTime(new SystemClock()),
        );
    }
}
