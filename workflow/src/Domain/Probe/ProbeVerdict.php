<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * What one expectation of the probe sweep came to (#119). A step not run is
 * never a pass: the sweep exits 0 only when every expectation is `PASS`.
 */
enum ProbeVerdict: string
{
    case Pass = 'PASS';
    case Fail = 'FAIL';
    case NotRun = 'NOT RUN';
}
