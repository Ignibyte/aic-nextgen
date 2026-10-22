<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * One line of the sweep's answer (#119): `PASS|FAIL|NOT RUN <scenario> <step> <what>`.
 */
final readonly class ProbeLine
{
    /**
     * @param ProbeVerdict $probeVerdict What the expectation came to.
     * @param string       $scenario     The scenario.
     * @param string       $step         The step, as its label and number.
     * @param string       $what         What was expected, and what was seen when it failed.
     */
    public function __construct(
        public ProbeVerdict $probeVerdict,
        public string $scenario,
        public string $step,
        public string $what,
    ) {
    }

    /**
     * @return string The line.
     */
    public function text(): string
    {
        return $this->probeVerdict->value . ' ' . $this->scenario . ' ' . $this->step . ': ' . $this->what;
    }
}
