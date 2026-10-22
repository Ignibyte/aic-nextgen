<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SeekerLog;
use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Domain\SeekerSeverity;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use PDO;
use PDOException;
use stdClass;

use function array_map;
use function count;

/**
 * Filed seeker ledgers in the evidence store (#103, Droost's
 * `seeker_finding`): one `seeker_inspection` row per filing, so a clean round
 * needs no sentinel row, and one `seeker_finding` row per finding, written
 * together in one transaction. Only the engine writes them.
 */
final readonly class SeekerStore implements SeekerLog
{
    /**
     * The inspection's insert.
     */
    private const string INSPECTION = 'INSERT INTO seeker_inspection (run_id, round, phase, inspector, dispatch, counted, '
        . 'subject_hash, digest, critical, high, medium, low, holding, observations, filed_at, ledger) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    /**
     * A finding's insert.
     */
    private const string FINDING = 'INSERT INTO seeker_finding (run_id, round, ref, severity, item, location, finding, status, reason) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

    /**
     * The newest inspection that counts.
     */
    private const string LATEST_COUNTED = 'SELECT * FROM seeker_inspection WHERE run_id = ? AND counted = 1 ORDER BY round DESC LIMIT 1';

    /**
     * One round's findings, in the ledger's order.
     */
    private const string FINDINGS = 'SELECT * FROM seeker_finding WHERE run_id = ? AND round = ? ORDER BY rowid';

    /**
     * @param string         $root           The project's root.
     * @param EvidenceSchema $evidenceSchema Opens the store.
     * @param SeekerRows     $seekerRows     Reads its rows back.
     */
    public function __construct(
        private string $root,
        private EvidenceSchema $evidenceSchema = new EvidenceSchema(),
        private SeekerRows $seekerRows = new SeekerRows(),
    ) {
    }

    /**
     * @param string           $runId            The run.
     * @param SeekerInspection $seekerInspection The filed ledger.
     *
     * @throws WorkflowRefusal When it cannot be written; then nothing is.
     */
    #[Override]
    public function file(string $runId, SeekerInspection $seekerInspection): void
    {
        try {
            $pdo = $this->pdo();
            $pdo->beginTransaction();
            $this->evidenceSchema->statement($pdo, self::INSPECTION, $this->inspectionRow($runId, $seekerInspection));

            foreach ($seekerInspection->seekerLedger->findings as $seekerFinding) {
                $this->evidenceSchema->statement($pdo, self::FINDING, $this->findingRow($runId, $seekerInspection->round, $seekerFinding));
            }

            $pdo->commit();
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('the seeker inspection was not written: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return SeekerInspection|null The newest inspection that counts.
     */
    #[Override]
    public function latestCounted(string $runId): ?SeekerInspection
    {
        $inspection = $this->rows(self::LATEST_COUNTED, [$runId])[0] ?? null;

        return $inspection instanceof JsonReader
            ? $this->seekerRows->inspection($inspection, $this->rows(self::FINDINGS, [$runId, $inspection->int('round')]))
            : null;
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return string When the newest ledger was filed; empty before the first.
     */
    #[Override]
    public function lastFiledAt(string $runId): string
    {
        $row = $this->rows('SELECT filed_at FROM seeker_inspection WHERE run_id = ? ORDER BY round DESC LIMIT 1', [$runId])[0] ?? null;

        return $row instanceof JsonReader
            ? $row->string('filed_at')
            : '';
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return list<string> Every filed ledger's digest.
     */
    #[Override]
    public function filedDigests(string $runId): array
    {
        return array_map(
            static fn (JsonReader $jsonReader): string => $jsonReader->string('digest'),
            $this->rows('SELECT digest FROM seeker_inspection WHERE run_id = ?', [$runId]),
        );
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return int The next round.
     */
    #[Override]
    public function nextRound(string $runId): int
    {
        return count($this->rows('SELECT round FROM seeker_inspection WHERE run_id = ?', [$runId])) + 1;
    }

    /**
     * @param string           $runId            The run.
     * @param SeekerInspection $seekerInspection The inspection.
     *
     * @return list<string|int> Its row's values.
     */
    private function inspectionRow(string $runId, SeekerInspection $seekerInspection): array
    {
        $seekerLedger = $seekerInspection->seekerLedger;

        return [
            $runId,
            $seekerInspection->round,
            $seekerInspection->phase->value,
            $seekerLedger->seekerInspector->value,
            $seekerInspection->seekerDispatch->value,
            $seekerInspection->counted() ? 1 : 0,
            $seekerLedger->subject,
            $seekerInspection->digest,
            $seekerLedger->open(SeekerSeverity::Critical),
            $seekerLedger->open(SeekerSeverity::High),
            $seekerLedger->open(SeekerSeverity::Medium),
            $seekerLedger->open(SeekerSeverity::Low),
            count($seekerLedger->holding()),
            $seekerLedger->observations,
            $seekerInspection->filedAt,
            $seekerLedger->section,
        ];
    }

    /**
     * @param string        $runId         The run.
     * @param int           $round         The round.
     * @param SeekerFinding $seekerFinding A finding.
     *
     * @return list<string|int> Its row's values.
     */
    private function findingRow(string $runId, int $round, SeekerFinding $seekerFinding): array
    {
        return [
            $runId,
            $round,
            $seekerFinding->ref,
            $seekerFinding->seekerSeverity->value,
            $seekerFinding->item,
            $seekerFinding->location,
            $seekerFinding->finding,
            $seekerFinding->seekerStatus->value,
            $seekerFinding->reason,
        ];
    }

    /**
     * @param string           $sql        A query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When it cannot be read.
     *
     * @return list<JsonReader> Its rows, each read through a JsonReader.
     */
    private function rows(string $sql, array $parameters): array
    {
        try {
            $objects = $this->evidenceSchema->statement($this->pdo(), $sql, $parameters)->fetchAll(PDO::FETCH_CLASS, stdClass::class);
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('the seeker inspections could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        $readers = [];

        foreach ($objects as $object) {
            if (!($object instanceof stdClass)) {
                continue;
            }

            $readers[] = new JsonReader($object, 'var/workflow/evidence.sqlite');
        }

        return $readers;
    }

    /**
     * @throws WorkflowRefusal When the store cannot be opened.
     *
     * @return PDO The store, at its current version.
     */
    private function pdo(): PDO
    {
        return $this->evidenceSchema->open($this->root . '/' . RunStateFile::DIRECTORY);
    }
}
