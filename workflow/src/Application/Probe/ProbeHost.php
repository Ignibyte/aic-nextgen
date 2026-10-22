<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

/**
 * Where the sweep runs (#119): only in AIC's own repository, never beside a
 * gate run, one sweep at a time.
 */
interface ProbeHost
{
    /**
     * @return bool Whether this is the package's own repository, whose fixture the scenarios plant into.
     */
    public function aic(): bool;

    /**
     * @return bool Whether a gate runner is live on this machine.
     */
    public function gateRunLive(): bool;

    /**
     * @return bool Whether the sweep's lock was taken; false when another sweep holds it.
     */
    public function lock(): bool;

    /**
     * Releases the sweep's lock.
     */
    public function unlock(): void;
}
