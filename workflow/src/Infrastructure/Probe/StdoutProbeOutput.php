<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeOutput;
use Override;

use function file_put_contents;

/**
 * The sweep's lines on standard output as they settle (#119).
 */
final readonly class StdoutProbeOutput implements ProbeOutput
{
    /**
     * @param string $line One line.
     */
    #[Override]
    public function line(string $line): void
    {
        file_put_contents('php://stdout', $line . "\n");
    }
}
