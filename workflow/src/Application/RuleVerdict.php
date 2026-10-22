<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\GateStatus;
use AIC\Workflow\Domain\ProcessResult;

use function array_slice;
use function explode;
use function implode;
use function strlen;
use function substr;
use function trim;

/**
 * A house rule's exit as a verdict (#104, Droost's rule-file contract with
 * AIC's deferral): 0 passed; 1 failed, its first lines the reason; 4 a
 * deferral, which is not a pass; 124 stopped after its timeout; 127 not
 * there; anything else (3 is the rule saying it cannot judge) did not run
 * to a verdict.
 */
final readonly class RuleVerdict
{
    /**
     * The characters of a rule's output kept, from its end.
     */
    private const int TRANSCRIPT = 4_000;

    /**
     * @param string        $name          The check, such as `rule:gate-suite`.
     * @param ProcessResult $processResult What the rule gave back.
     * @param string        $subjectHash   The tree it judged.
     *
     * @return CheckRecord The verdict, its output kept as the transcript.
     */
    public function record(string $name, ProcessResult $processResult, string $subjectHash): CheckRecord
    {
        $gateStatus = $this->status($processResult->exitCode);
        $output = $processResult->output;
        $transcript = strlen($output) > self::TRANSCRIPT
            ? substr($output, -self::TRANSCRIPT)
            : $output;

        return new CheckRecord(
            $name,
            $gateStatus->checkState(),
            $gateStatus->fault(),
            $this->summary($processResult),
            $subjectHash,
            $transcript,
        );
    }

    /**
     * @param int $exitCode A rule's exit.
     *
     * @return GateStatus What it means.
     */
    public function status(int $exitCode): GateStatus
    {
        return match ($exitCode) {
            0 => GateStatus::Passed,
            1 => GateStatus::Failed,
            4 => GateStatus::Deferred,
            127 => GateStatus::ErrorToolMissing,
            default => GateStatus::ErrorToolFailed,
        };
    }

    /**
     * @param ProcessResult $processResult What the rule gave back.
     *
     * @return string Why, in a line.
     */
    private function summary(ProcessResult $processResult): string
    {
        $lines = implode(' ', array_slice(explode("\n", trim($processResult->output)), 0, 5));
        $quiet = trim($processResult->output) === '';

        return match ($processResult->exitCode) {
            0 => 'passed.' . ($quiet ? '' : ' ' . $lines),
            1 => 'failed: ' . ($quiet ? 'the rule printed no reason.' : $lines),
            4 => 'examined nothing; a deferral is not a pass.',
            124 => 'killed after its timeout, no verdict.',
            127 => 'the rule is not there.',
            default => 'did not run to a verdict (exit ' . $processResult->exitCode . ')' . ($quiet ? '.' : ': ' . $lines),
        };
    }
}
