<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\FrozenSpec;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_keys;
use function array_merge;
use function count;
use function explode;
use function hash;
use function implode;
use function json_encode;
use function str_contains;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The operator's amendment of a frozen spec row (#267): a row planning got wrong, such as an entry
 * point's status, is fixed from a terminal with a reason, instead of a reset and a new run. The row
 * is named by its markdown line as written. The engine swaps that line in the freeze and in the
 * run's spec, and the swap must change exactly one of the rows the freeze holds, so it cannot slip
 * in a second edit. The freeze keeps its time and gate list, and the event keeps both rows and the
 * reason. Only from a terminal: the agent's shell is refused, and the guard walls it too.
 */
final readonly class SpecAmend
{
    /**
     * @param RunStore    $runStore    The run and its spec.
     * @param EventLog    $eventLog    The `spec.amended` event.
     * @param RunHost     $runHost     Whether an operator is at a terminal.
     * @param EvidenceLog $evidenceLog The freeze.
     * @param SpecFile    $specFile    Reads and parses specs.
     */
    public function __construct(
        private RunStore $runStore,
        private EventLog $eventLog,
        private RunHost $runHost,
        private EvidenceLog $evidenceLog,
        private SpecFile $specFile,
    ) {
    }

    /**
     * @param string $row    The frozen line, as the spec writes it.
     * @param string $newRow The line to put in its place.
     * @param string $reason Why.
     *
     * @throws WorkflowRefusal When no operator is at a terminal, no run is open past plan, or the change is not one row.
     *
     * @return list<string> What it did, then any notes.
     */
    public function amend(string $row, string $newRow, string $reason): array
    {
        $runState = $this->openRun($reason);
        $runId = $runState->runStart->runId;
        $frozenSpec = $this->evidenceLog->frozen($runId)
            ?? throw new WorkflowRefusal('run ' . $runId . ' has no freeze to amend.');
        $text = $this->swapped($frozenSpec->text, $row, $newRow);
        $this->oneRow($frozenSpec->text, $text);
        $this->evidenceLog->freeze($runId, new FrozenSpec($text, hash('xxh128', $text), $frozenSpec->frozenAt, $frozenSpec->gates));

        return [
            'the freeze of run ' . $runId . ' now holds "' . $newRow . '" in place of "' . $row . '".',
            $this->spec($runState, $row, $newRow),
            ...$this->logged($runState, $row, $newRow, $reason),
        ];
    }

    /**
     * @param RunState $runState The run, its freeze just amended.
     * @param string   $row      The frozen line it replaced.
     * @param string   $newRow   The line in its place.
     * @param string   $reason   Why, in the operator's words.
     *
     * @return list<string> A note when the event could not be logged; none when it was.
     */
    private function logged(RunState $runState, string $row, string $newRow, string $reason): array
    {
        $phase = $runState->currentPhase;
        $note = $this->eventLog->append($runState, EventType::SpecAmended, json_encode([
            'new_row' => $newRow,
            'phase' => $phase instanceof Phase ? $phase->value : null,
            'reason' => $reason,
            'row' => $row,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $note === ''
            ? []
            : [$note];
    }

    /**
     * @param string $reason Why, which must be given.
     *
     * @throws WorkflowRefusal When no operator is at a terminal, there is no reason, or no run is open past plan.
     *
     * @return RunState The open run.
     */
    private function openRun(string $reason): RunState
    {
        if (!$this->runHost->operatorTerminal()) {
            throw new WorkflowRefusal("workflow:amend is the operator's: run it from a terminal, not an agent's shell.");
        }

        if (trim($reason) === '') {
            throw new WorkflowRefusal('give the reason: bin/aic workflow:amend "<row>" "<new row>" "<reason>".');
        }

        $runState = $this->runStore->load();

        if (!$runState instanceof RunState) {
            throw new WorkflowRefusal('no run is open; there is no freeze to amend.');
        }

        return $runState->currentPhase === Phase::Plan
            ? throw new WorkflowRefusal('the run is at plan, where nothing is frozen yet: the agent edits the spec.')
            : $runState;
    }

    /**
     * @param string $text   A spec.
     * @param string $row    The line to replace, which must be one whole line of it, once.
     * @param string $newRow The line to put in its place: one line, not empty.
     *
     * @throws WorkflowRefusal When the row is not one line of the text, or the new row is not one line.
     *
     * @return string The text with the line replaced.
     */
    private function swapped(string $text, string $row, string $newRow): string
    {
        if (trim($newRow) === '' || str_contains($newRow, "\n")) {
            throw new WorkflowRefusal('the new row is one line, not empty.');
        }

        $lines = explode("\n", $text);
        $found = array_keys($lines, $row, true);

        if (count($found) !== 1) {
            throw new WorkflowRefusal('"' . $row . '" is not one whole line of the frozen spec: copy the row as the spec writes it.');
        }

        $lines[$found[0]] = $newRow;

        return implode("\n", $lines);
    }

    /**
     * @param string $frozen  The frozen spec.
     * @param string $amended It with the line replaced.
     *
     * @throws WorkflowRefusal When the amended spec is no contract, or the change is not one frozen row for another.
     */
    private function oneRow(string $frozen, string $amended): void
    {
        $contract = $this->specFile->contract($amended);

        if ($contract->problems() !== []) {
            throw new WorkflowRefusal('the amended spec is no contract: ' . implode('; ', $contract->problems()));
        }

        $changed = $this->changed($this->specFile->contract($frozen)->frozenRows(), $contract->frozenRows());

        if ($changed !== [[1, 1]]) {
            throw new WorkflowRefusal('the change is not one frozen row for another in one section; amend one row at a time.');
        }
    }

    /**
     * @param array<string, list<string>> $before The frozen rows, by section.
     * @param array<string, list<string>> $after  The amended rows, by section.
     *
     * @return list<array{int, int}> For each section that changed, the rows it lost and the rows it gained.
     */
    private function changed(array $before, array $after): array
    {
        $changed = [];

        foreach (array_keys(array_merge($before, $after)) as $section) {
            $lost = count(array_diff($before[$section] ?? [], $after[$section] ?? []));
            $gained = count(array_diff($after[$section] ?? [], $before[$section] ?? []));

            if ($lost + $gained === 0) {
                continue;
            }

            $changed[] = [$lost, $gained];
        }

        return $changed;
    }

    /**
     * @param RunState $runState The open run.
     * @param string   $row      The replaced line.
     * @param string   $newRow   The line in its place.
     *
     * @throws WorkflowRefusal When the spec cannot be written.
     *
     * @return string What happened to the run's spec.
     */
    private function spec(RunState $runState, string $row, string $newRow): string
    {
        $specPath = $runState->runStart->specPath;
        $text = $this->specFile->text($specPath) ?? '';
        $found = array_keys(explode("\n", $text), $row, true);

        if (count($found) !== 1) {
            return $specPath . ' does not hold the row once; the agent writes the new row there.';
        }

        $this->runStore->saveSpec($runState, $this->swapped($text, $row, $newRow));

        return $specPath . ' holds the new row.';
    }
}
