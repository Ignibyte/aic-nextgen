<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\GateResult;
use AIC\Workflow\Domain\GateRun;
use AIC\Workflow\Domain\GateStatus;
use AIC\Workflow\Domain\ProcessResult;
use AIC\Workflow\Domain\RegexCaptures;

use function array_count_values;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_quote;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Reads a `composer run gate` (#101): the runner's `gate-result:` lines and
 * its `gate-summary:` line, held to each other, to its exit code and to the
 * gate names frozen at plan. Anything that does not add up did not run to a
 * verdict. This is Droost's gate-file contract, except that a deferral is
 * not a pass.
 */
final readonly class GateSummary
{
    use RegexCaptures;

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
        $results = [];

        foreach (self::regexRows('/^gate-result: (gate:\\d+) (passed|deferred|failed)$/m', $output) as $match) {
            if (!array_key_exists(1, $match) || !array_key_exists(2, $match)) {
                continue;
            }

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
        $missing = array_diff($gates, array_keys($results));

        return match (true) {
            !in_array($exitCode, [0, 1], true) => 'it exited ' . $exitCode . '.',
            $summary === '' => 'it printed no gate-summary line.',
            $missing !== [] => implode(', ', $missing) . ' printed no result.',
            !$this->consistent($exitCode, $summary, $results) => 'its results disagree with "' . $summary . '" or exit ' . $exitCode . '.',
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
        $line = self::regexMatch('/^' . preg_quote($gate, '/') . ' — (.*deferring.*)$/m', $output);

        return $gateStatus === GateStatus::Deferred && array_key_exists(1, $line)
            ? $gate . ' deferred: ' . $line[1]
            : $gate . ' ' . $gateStatus->value . '.';
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

    /**
     * @param int                   $exitCode The actual runner exit.
     * @param string                $summary  Its final summary line.
     * @param array<string, string> $results  Its parsed result statuses.
     *
     * @return bool Whether all counts and the exit agree with that summary.
     */
    private function consistent(int $exitCode, string $summary, array $results): bool
    {
        $counts = array_count_values($results);
        $passed = $counts['passed'] ?? 0;
        $deferred = $counts['deferred'] ?? 0;
        $failed = $counts['failed'] ?? 0;
        $prefix = preg_match('/^gate-summary: ' . $passed . ' passed, ' . $deferred . ' deferred/', $summary) === 1;
        $failures = preg_match('/, ' . $failed . ' failed \\(/', $summary) === 1;

        return $prefix && (($failed > 0) === $failures) && (($exitCode === 1) === ($failed > 0));
    }
}
