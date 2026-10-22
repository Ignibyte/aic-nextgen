<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\ProcessResult;

/**
 * The project's gate suite as the engine runs it (#101): one command, the
 * gate names it holds, and whether another run of it is live.
 */
interface GateExecutor
{
    /**
     * @return list<string> The project's `gate:N` names, in run order.
     */
    public function names(): array;

    /**
     * @return bool Whether a gate run is already live on this machine.
     */
    public function busy(): bool;

    /**
     * @return ProcessResult The whole suite's exit code and output.
     */
    public function run(): ProcessResult;
}
