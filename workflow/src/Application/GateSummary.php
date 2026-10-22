<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\GateResult;
use AIC\Workflow\Domain\GateRun;
use AIC\Workflow\Domain\GateStatus;
use AIC\Workflow\Domain\ProcessResult;

use function array_count_values;
use function array_diff;
use function array_keys;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

use const PREG_SET_ORDER;

/**
 * Reads a `composer run gate` (#101): the runner's `gate-result:` lines and
 * its `gate-summary:` line, held to each other, to its exit code and to the
 * gate names frozen at plan. Anything that does not add up did not run to a
 * verdict. This is Droost's gate-file contract, except that a deferral is
 * not a pass.
 */
final readonly class GateSummary
{
    /**
     * The characters of a gate's output kept as its transcript, from its end.
     */
    private const int TRANSCRIPT = 4_000;

    /**
     * @param ProcessResult $processResult The run.
     * @param list<string>  $gates         The gate names frozen at plan.
     *
     * @return GateRun What it found.
     */
    public function read(ProcessResult $processResult, array $gates): GateRun
    {
        $exitCode = $processResult->exitCode;
        $output = $processResult->output;

        if ($exitCode === 127) {
            return new GateRun(GateStatus::ErrorToolMissing, 'composer run gate could not start (exit 127).', []);
        }

        if ($exitCode === 124) {
            return new GateRun(GateStatus::ErrorToolFailed, 'the gate run passed its timeout and was stopped (exit 124).', []);
        }

        $summary = $this->summary($output);
        $results = $this->results($output);
        $problem = $this->problem($exitCode, $summary, $results, $gates);

        return $problem === ''
            ? new GateRun($this->aggregate($results), $summary, $this->gateResults($results, $output))
            : new GateRun(GateStatus::ErrorToolFailed, 'the gate run did not run to a verdict: ' . $problem, []);
    }

    /**
     * @param string $output The run's output.
     *
     * @return string Its last `gate-summary:` line; empty when there is none.
     */
    private function summary(string $output): string
    {
        $summary = '';

        foreach (explode("\n", $output) as $line) {
            $summary = str_starts_with($line, 'gate-summary: ')
                ? rtrim($line)
                : $summary;
        }

        return $summary;
    }

    /**
     * @param string $output The run's output.
     *
     * @return array<string, string> Each `gate-result:` line's status, by gate.
     */
    private function results(string $output): array
    {
        preg_match_all('/^gate-result: (gate:\\d+) (passed|deferred|failed)$/m', $output, $matches, PREG_SET_ORDER);
        $results = [];

        foreach ($matches as $match) {
            $results[$match[1]] = $match[2];
        }

        return $results;
    }

    /**
     * @param int                   $exitCode The runner's exit code.
     * @param string                $summary  Its summary line; empty when it printed none.
     * @param array<string, string> $results  Its result lines.
     * @param list<string>          $gates    The frozen gate names.
     *
     * @return string What does not add up; empty when it all does.
     */
    private function problem(int $exitCode, string $summary, array $results, array $gates): string
    {
        $counts = array_count_values($results) + ['deferred' => 0, 'failed' => 0, 'passed' => 0];
        $missing = array_diff($gates, array_keys($results));

        return match (true) {
            !in_array($exitCode, [0, 1], true) => 'it exited ' . $exitCode . '.',
            $summary === '' => 'it printed no gate-summary line.',
            $missing !== [] => implode(', ', $missing) . ' printed no result.',
            preg_match('/^gate-summary: ' . $counts['passed'] . ' passed, ' . $counts['deferred'] . ' deferred/', $summary) !== 1,
            ($counts['failed'] > 0) !== (preg_match('/, ' . $counts['failed'] . ' failed \\(/', $summary) === 1),
            ($exitCode === 1) !== ($counts['failed'] > 0) => 'its results disagree with "' . $summary . '" or exit ' . $exitCode . '.',
            default => '',
        };
    }

    /**
     * @param array<string, string> $results The result lines.
     *
     * @return GateStatus Failed when any gate failed, deferred when any deferred, passed otherwise.
     */
    private function aggregate(array $results): GateStatus
    {
        return match (true) {
            in_array('failed', $results, true) => GateStatus::Failed,
            in_array('deferred', $results, true) => GateStatus::Deferred,
            default => GateStatus::Passed,
        };
    }

    /**
     * @param array<string, string> $results The result lines.
     * @param string                $output  The run's output.
     *
     * @return list<GateResult> Each gate's result, with why and its transcript.
     */
    private function gateResults(array $results, string $output): array
    {
        $gateResults = [];

        foreach ($results as $gate => $status) {
            $gateStatus = match ($status) {
                'passed' => GateStatus::Passed,
                'deferred' => GateStatus::Deferred,
                default => GateStatus::Failed,
            };
            $gateResults[] = new GateResult($gate, $gateStatus, $this->why($gate, $gateStatus, $output), $this->transcript($gate, $output));
        }

        return $gateResults;
    }

    /**
     * @param string     $gate       A gate.
     * @param GateStatus $gateStatus Its verdict.
     * @param string     $output     The run's output.
     *
     * @return string Why: its deferral line when it deferred.
     */
    private function why(string $gate, GateStatus $gateStatus, string $output): string
    {
        if (
            $gateStatus === GateStatus::Deferred
            && preg_match('/^' . preg_quote($gate, '/') . ' — (.*deferring.*)$/m', $output, $line) === 1
        ) {
            return $gate . ' deferred: ' . $line[1];
        }

        return $gate . ' ' . $gateStatus->value . '.';
    }

    /**
     * @param string $gate   A gate.
     * @param string $output The run's output.
     *
     * @return string Its section, from its `=== gate:N ===` heading to the next, its last characters only.
     */
    private function transcript(string $gate, string $output): string
    {
        $heading = '=== ' . $gate . ' ===';
        $start = strpos($output, $heading);

        if ($start === false) {
            return '';
        }

        $start += strlen($heading);
        $end = strpos($output, "\n=== gate:", $start);
        $end = $end === false
            ? strpos($output, "\ngate-result:", $start)
            : $end;

        $section = $end === false
            ? substr($output, $start)
            : substr($output, $start, $end - $start);

        return strlen($section) > self::TRANSCRIPT
            ? substr($section, -self::TRANSCRIPT)
            : $section;
    }
}
