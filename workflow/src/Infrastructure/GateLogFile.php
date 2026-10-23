<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\GateLog;
use AIC\Workflow\Domain\GateLogEntry;
use AIC\Workflow\Domain\GateLogQuery;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use PDOException;
use stdClass;

/** Reads one prepared gate lookup through a connection that cannot write or migrate evidence. */
final readonly class GateLogFile implements GateLog
{
    /**
     * @param ReadOnlyEvidence $readOnlyEvidence Opens the existing file without writes.
     * @param SqliteStatements $sqliteStatements Executes prepared selectors.
     * @param EvidenceRows     $evidenceRows     Validates stored outcome and provenance values.
     */
    public function __construct(
        private ReadOnlyEvidence $readOnlyEvidence,
        private SqliteStatements $sqliteStatements = new SqliteStatements(),
        private EvidenceRows $evidenceRows = new EvidenceRows(),
    ) {
    }

    /**
     * @param GateLogQuery $gateLogQuery The selectors.
     * @param string       $runId        The resolved run.
     *
     * @throws WorkflowRefusal When the file or selected row cannot be read.
     *
     * @return GateLogEntry|null The latest matching row.
     */
    #[Override]
    public function find(GateLogQuery $gateLogQuery, string $runId): ?GateLogEntry
    {
        try {
            [$pdo, $version] = $this->readOnlyEvidence->open();
            $statement = $this->sqliteStatements->run($pdo, $this->sql($version), $this->parameters($gateLogQuery, $runId));
            $row = $statement->fetchObject();
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return $row instanceof stdClass
            ? $this->entry(new JsonReader($row, 'var/workflow/evidence.sqlite'))
            : null;
    }

    /**
     * @param GateLogQuery $gateLogQuery The validated selectors.
     * @param string       $runId        The selected run.
     *
     * @return list<string> Values for the fixed prepared lookup.
     */
    private function parameters(GateLogQuery $gateLogQuery, string $runId): array
    {
        $phase = $gateLogQuery->phase;
        $phaseValue = $phase instanceof Phase
            ? $phase->value
            : '';
        $attempt = $gateLogQuery->attempt === null
            ? ''
            : (string) $gateLogQuery->attempt;

        return [$runId, $gateLogQuery->gate, $phaseValue, $phaseValue, $attempt, $attempt];
    }

    /**
     * @param int $version The existing schema version.
     *
     * @return string The fixed lookup, with old schemas explicitly reporting unknown capture completeness.
     */
    private function sql(int $version): string
    {
        $complete = $version >= 4
            ? 'c.transcript_complete'
            : '0 AS transcript_complete';

        return 'SELECT c.id, c.run_id, c.phase, c.attempt, c.name, c.state, c.fault, c.summary, c.subject_hash, '
            . 'c.adjudicated_at, ' . $complete . ", COALESCE(t.output, '') AS output, "
            . 'CASE WHEN t.check_id IS NULL THEN 0 ELSE 1 END AS transcript_present '
            . 'FROM check_result c LEFT JOIN transcript t ON t.check_id = c.id '
            . "WHERE c.run_id = ? AND c.name = ? AND (? = '' OR c.phase = ?) AND (? = '' OR c.attempt = ?) "
            . 'ORDER BY c.id DESC LIMIT 1';
    }

    /**
     * @param JsonReader $jsonReader The stored row.
     *
     * @throws WorkflowRefusal When a stored field is malformed.
     *
     * @return GateLogEntry Its typed metadata and exact stored output.
     */
    private function entry(JsonReader $jsonReader): GateLogEntry
    {
        $checkRecord = $this->evidenceRows->record($jsonReader);
        $present = $jsonReader->int('transcript_present') === 1;

        if (
            $jsonReader->int('id') < 1
            || $jsonReader->int('attempt') < 1
            || $checkRecord->transcriptComplete && !$present
        ) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite holds malformed record identity or capture provenance.');
        }

        return new GateLogEntry(
            $jsonReader->int('id'),
            $jsonReader->string('run_id'),
            $this->phase($jsonReader->string('phase')),
            $jsonReader->int('attempt'),
            $jsonReader->string('adjudicated_at'),
            $checkRecord,
            $present,
        );
    }

    /**
     * @param string $phase The stored phase.
     *
     * @throws WorkflowRefusal When the phase is unknown.
     *
     * @return Phase The recorded phase.
     */
    private function phase(string $phase): Phase
    {
        return match ($phase) {
            'plan' => Phase::Plan,
            'code' => Phase::Code,
            'check' => Phase::Check,
            'complete' => Phase::Complete,
            default => throw new WorkflowRefusal('var/workflow/evidence.sqlite holds an unknown phase.'),
        };
    }
}
