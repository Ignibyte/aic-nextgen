<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeStep;
use AIC\Workflow\Domain\Probe\ProbeVerdict;

use function array_fill;
use function sprintf;

/**
 * The sweep's lines, made one way (#119): a step's name is its number and
 * its label, and an expectation holds or it does not.
 */
final readonly class ProbeLines
{
    /**
     * @param int       $number    The step's place in its scenario, from 1.
     * @param ProbeStep $probeStep The step.
     *
     * @return string Its name in a line, such as `03 workflow:run`.
     */
    public function step(int $number, ProbeStep $probeStep): string
    {
        return sprintf('%02d %s', $number, $probeStep->label());
    }

    /**
     * @param bool   $held     Whether the expectation held.
     * @param string $scenario The scenario.
     * @param string $step     The step's name.
     * @param string $what     What was expected.
     * @param string $seen     What was seen, printed when it did not hold.
     *
     * @return ProbeLine The line.
     */
    public function held(bool $held, string $scenario, string $step, string $what, string $seen = ''): ProbeLine
    {
        return $held
            ? new ProbeLine(ProbeVerdict::Pass, $scenario, $step, $what)
            : new ProbeLine(ProbeVerdict::Fail, $scenario, $step, $what . ($seen === '' ? '' : '; saw: ' . $seen));
    }

    /**
     * @param string $scenario The scenario.
     * @param string $step     The step's name.
     * @param int    $count    How many of its expectations were never reached.
     * @param string $why      Why.
     *
     * @return list<ProbeLine> One line each.
     */
    public function notRun(string $scenario, string $step, int $count, string $why): array
    {
        return $count < 1
            ? []
            : array_fill(0, $count, new ProbeLine(ProbeVerdict::NotRun, $scenario, $step, $why));
    }
}
