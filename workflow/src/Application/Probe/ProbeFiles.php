<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;

/**
 * Files in a scenario's app, written as the operator (#119), and the sweep's
 * evidence in this repository's `var/probe/<scenario>/`, which outlives the
 * app.
 */
interface ProbeFiles
{
    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A path in it.
     *
     * @return string|null Its bytes; null when it is not a readable file.
     */
    public function read(ProbeApp $probeApp, string $path): ?string;

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A path in it.
     * @param string   $bytes    What it holds.
     *
     * @return bool Whether it was written, its folders made.
     */
    public function write(ProbeApp $probeApp, string $path, string $bytes): bool;

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A file in it.
     *
     * @return bool Whether it was there and is gone.
     */
    public function delete(ProbeApp $probeApp, string $path): bool;

    /**
     * @param string $scenario The scenario.
     * @param string $file     The evidence's file name, such as `03-workflow-run.json`.
     * @param string $bytes    What it holds.
     */
    public function evidence(string $scenario, string $file, string $bytes): void;

    /**
     * @param string $scenario The scenario, whose evidence from an earlier sweep is removed.
     */
    public function fresh(string $scenario): void;
}
