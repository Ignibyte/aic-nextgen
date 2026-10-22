<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * How a run is driven (#117): agentic runs plan to complete without asking;
 * interactive waits for the operator to confirm each phase exit. Frozen into
 * `run.json` at begin.
 */
enum Mode: string
{
    case Agentic = 'agentic';
    case Interactive = 'interactive';

    /**
     * @return bool Whether every phase exit waits for the operator's confirmation.
     */
    public function confirmsExits(): bool
    {
        return $this === self::Interactive;
    }
}
