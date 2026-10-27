<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\EvidenceLog;
use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\FrozenSpec;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use PDOException;
use stdClass;

use function explode;
use function implode;

/**
 * The evidence store (#101, Droost's `EvidenceStore` trimmed): SQLite in the
 * state directory, written only by the engine. Every verdict is a row with
 * the subject hash it judged; a verdict is green and fresh only while its
 * newest row is satisfied on the tree as it is.
 */
final readonly class EvidenceStore implements EvidenceLog
{
    /**
     * @param string         $root           The project's root.
     * @param EvidenceSchema $evidenceSchema Opens the store.
     * @param EvidenceRows   $evidenceRows   Reads its rows.
     */
    public function __construct(
        private string $root,
        private EvidenceSchema $evidenceSchema = new EvidenceSchema(),
        private EvidenceRows $evidenceRows = new EvidenceRows(),
    ) {
    }

    /**
     * @param string     $runId      The run.
     * @param FrozenSpec $frozenSpec What planning ended on.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    #[Override]
    public function freeze(string $runId, FrozenSpec $frozenSpec): void
    {
        $this->write(
            'INSERT OR REPLACE INTO run (run_id, spec_text, spec_hash, spec_frozen_at, gate_list) VALUES (?, ?, ?, ?, ?)',
            [$runId, $frozenSpec->text, $frozenSpec->hash, $frozenSpec->frozenAt, implode(',', $frozenSpec->gates)],
        );
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return FrozenSpec|null What it froze.
     */
    #[Override]
    public function frozen(string $runId): ?FrozenSpec
    {
        $row = $this->first('SELECT spec_text, spec_hash, spec_frozen_at, gate_list FROM run WHERE run_id = ?', [$runId]);

        return $row instanceof JsonReader
            ? new FrozenSpec(
                $row->string('spec_text'),
                $row->string('spec_hash'),
                $row->string('spec_frozen_at'),
                explode(',', $row->string('gate_list')),
            )
            : null;
    }

    /**
     * @param string      $runId       The run.
     * @param Phase       $phase       The phase.
     * @param int         $attempt     The attempt.
     * @param CheckRecord $checkRecord The verdict.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    #[Override]
    public function record(string $runId, Phase $phase, int $attempt, CheckRecord $checkRecord): void
    {
        $this->write(
            'INSERT INTO check_result (run_id, phase, attempt, name, state, fault, summary, subject_hash, transcript_complete) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $runId,
                $phase->value,
                $attempt,
                $checkRecord->name,
                $checkRecord->checkState->value,
                $checkRecord->fault->value,
                $checkRecord->summary,
                $checkRecord->subjectHash,
                $checkRecord->transcriptComplete ? 1 : 0,
            ],
            $checkRecord->transcript,
            $checkRecord->transcriptComplete,
        );
    }

    /**
     * @param string $runId The run.
     * @param string $name  A check.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return CheckRecord|null Its newest verdict.
     */
    #[Override]
    public function latest(string $runId, string $name): ?CheckRecord
    {
        $row = $this->first(
            "SELECT c.name, c.state, c.fault, c.summary, c.subject_hash, c.transcript_complete, COALESCE(t.output, '') AS output "
                . 'FROM check_result c LEFT JOIN transcript t ON t.check_id = c.id '
                . 'WHERE c.run_id = ? AND c.name = ? ORDER BY c.id DESC LIMIT 1',
            [$runId, $name],
        );

        return $row instanceof JsonReader
            ? $this->evidenceRows->record($row)
            : null;
    }

    /**
     * @param string $runId The run.
     * @param Phase  $phase A phase.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return array<string, int> Failures per check at the phase.
     */
    #[Override]
    public function failures(string $runId, Phase $phase): array
    {
        $failures = [];
        $rows = $this->all(
            'SELECT name, COUNT(*) AS failures FROM check_result WHERE run_id = ? AND phase = ? AND state = ? GROUP BY name',
            [$runId, $phase->value, CheckState::Blocked->value],
        );

        foreach ($rows as $row) {
            $failures[$row->string('name')] = $row->int('failures');
        }

        return $failures;
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return int The guard's rows for it the store holds.
     */
    #[Override]
    public function toolCallCount(string $runId): int
    {
        $row = $this->first('SELECT COUNT(*) AS calls FROM tool_call WHERE run_id = ?', [$runId]);

        return $row instanceof JsonReader
            ? $row->int('calls')
            : 0;
    }

    /**
     * @param string         $runId The run.
     * @param list<GuardRow> $rows  The guard's rows for it, in order.
     *
     * @throws WorkflowRefusal When they cannot be written.
     */
    #[Override]
    public function recordToolCalls(string $runId, array $rows): void
    {
        foreach ($rows as $row) {
            $this->write(
                'INSERT INTO tool_call (run_id, phase, tool, at, hook, verdict, rule) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$runId, $row->phase, $row->tool, $row->time, $row->hook, $row->verdict, $row->rule],
            );
        }
    }

    /**
     * @param string           $sql        An insert.
     * @param list<string|int> $parameters Its values.
     * @param string           $transcript The verdict's output, kept beside it when there is one.
     * @param bool             $complete   Whether an empty transcript is a complete observed capture.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    private function write(string $sql, array $parameters, string $transcript = '', bool $complete = false): void
    {
        try {
            $pdo = $this->evidenceSchema->open($this->root . '/' . RunStateFile::DIRECTORY);
            $this->evidenceSchema->statement($pdo, $sql, $parameters);

            if ($transcript !== '' || $complete) {
                $this->evidenceSchema->statement(
                    $pdo,
                    'INSERT INTO transcript (check_id, output) VALUES (?, ?)',
                    [(int) $pdo->lastInsertId(), $transcript],
                );
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite was not written: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    /**
     * @param string           $sql        A query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When it cannot be read.
     *
     * @return JsonReader|null Its first row.
     */
    private function first(string $sql, array $parameters): ?JsonReader
    {
        return $this->all($sql, $parameters)[0] ?? null;
    }

    /**
     * @param string           $sql        A query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When it cannot be read.
     *
     * @return list<JsonReader> Its rows.
     */
    private function all(string $sql, array $parameters): array
    {
        try {
            $statement = $this->evidenceSchema->statement(
                $this->evidenceSchema->open($this->root . '/' . RunStateFile::DIRECTORY),
                $sql,
                $parameters,
            );
            $rows = [];

            while (($row = $statement->fetchObject()) instanceof stdClass) {
                $rows[] = new JsonReader($row, 'var/workflow/evidence.sqlite');
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return $rows;
    }
}
