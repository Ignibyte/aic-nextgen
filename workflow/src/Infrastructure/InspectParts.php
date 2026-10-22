<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeQueryRecorder;
use AIC\Workflow\Application\Console\InspectRun;
use AIC\Workflow\Application\UtcTime;

/**
 * The inspect commands' shared run (#109), kept apart so `InspectKernel`
 * stays under PHPMD's coupling cap: the surface, and the recorder that logs
 * each lookup in the open run.
 */
final readonly class InspectParts
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return InspectRun The run every inspect command goes through.
     */
    public function inspectRun(): InspectRun
    {
        $localHost = new LocalHost($this->root);
        $eventLogFile = new EventLogFile($this->root, new UtcTime(new SystemClock()), $localHost);

        $codeQueryRecorder = new CodeQueryRecorder(new RunStateFile($this->root), $eventLogFile);

        return new InspectRun(new SurfaceEntries($this->root, $localHost), $codeQueryRecorder);
    }
}
