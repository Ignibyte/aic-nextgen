<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\RunAnswer;

/**
 * The JSON the sweep reads from the engine's answers (#119).
 */
interface ProbeJson
{
    /**
     * @param string $output What `workflow:run` printed.
     *
     * @return RunAnswer|null Its outcome and rows; null when it printed no envelope.
     */
    public function run(string $output): ?RunAnswer;

    /**
     * @param string $output What `workflow:seeker-brief` printed.
     *
     * @return string The brief's subject; empty when it printed none.
     */
    public function subject(string $output): string;
}
