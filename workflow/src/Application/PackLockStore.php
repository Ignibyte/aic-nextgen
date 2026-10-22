<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\PackLock;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Where the pack's lock is kept (#105).
 */
interface PackLockStore
{
    /**
     * @throws WorkflowRefusal When it is there and is not a lock.
     *
     * @return PackLock The lock; empty when there is none yet.
     */
    public function load(): PackLock;

    /**
     * @param PackLock $packLock The lock init leaves.
     *
     * @throws WorkflowRefusal When it cannot be written.
     *
     * @return InstallLine Wrote when it changed, current when it did not.
     */
    public function save(PackLock $packLock): InstallLine;
}
