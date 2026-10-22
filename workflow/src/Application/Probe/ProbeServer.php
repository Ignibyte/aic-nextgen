<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;

/**
 * The app served for the rendered check (#119): `php -S` on the app's port,
 * stopped by its process id, never by a pattern.
 */
interface ProbeServer
{
    /**
     * @param ProbeApp              $probeApp The app.
     * @param array<string, string> $env      Variables it serves with.
     *
     * @return bool Whether it answers on the app's port.
     */
    public function start(ProbeApp $probeApp, array $env): bool;

    /**
     * @param ProbeApp $probeApp The app.
     *
     * @return bool Whether the server it started is gone; true when none was started.
     */
    public function stop(ProbeApp $probeApp): bool;
}
