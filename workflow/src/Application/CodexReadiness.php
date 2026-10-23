<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/** Read the actual client and installed callback readiness without changing trust. */
interface CodexReadiness
{
    /** @return list<string> Problems that hold a fresh Codex workflow launch, empty when ready. */
    public function problems(): array;
}
