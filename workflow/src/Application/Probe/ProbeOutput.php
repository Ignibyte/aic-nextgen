<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

/**
 * The sweep's lines as they happen (#119): a sweep runs for half an hour,
 * so each line is written when its expectation is settled, not at the end.
 */
interface ProbeOutput
{
    /**
     * @param string $line One line.
     */
    public function line(string $line): void;
}
