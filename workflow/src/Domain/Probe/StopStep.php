<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Stops the server a `ServeStep` started, by its process id (#119). It holds
 * when the server is gone.
 */
final readonly class StopStep implements ProbeStep
{
    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'stop the server';
    }

    /**
     * @return int One.
     */
    #[Override]
    public function expectations(): int
    {
        return 1;
    }
}
