<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use PDO;
use PDOException;
use PDOStatement;

/**
 * The evidence store's file and schema (#101): `var/workflow/evidence.sqlite`
 * at version 4 (#144 added observed transcript completeness),
 * forward-only. A store from a newer engine is refused rather than read
 * wrong. Its statements are prepared and executed here, failing
 * loudly.
 */
final readonly class EvidenceSchema
{
    /**
     * The version this engine writes and reads.
     */
    private const int VERSION = 4;

    /**
     * Version 1: what planning froze, each verdict, each verdict's output, and the guard's ledger.
     */
    private const string TABLES = <<<'SQL'
        CREATE TABLE IF NOT EXISTS run (
            run_id TEXT PRIMARY KEY,
            spec_text TEXT NOT NULL,
            spec_hash TEXT NOT NULL,
            spec_frozen_at TEXT NOT NULL,
            gate_list TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS check_result (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id TEXT NOT NULL,
            phase TEXT NOT NULL,
            attempt INTEGER NOT NULL,
            name TEXT NOT NULL,
            state TEXT NOT NULL,
            fault TEXT NOT NULL,
            summary TEXT NOT NULL,
            subject_hash TEXT NOT NULL,
            adjudicated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
        );
        CREATE INDEX IF NOT EXISTS check_result_by_name ON check_result (run_id, name, id);
        CREATE TABLE IF NOT EXISTS transcript (
            check_id INTEGER PRIMARY KEY REFERENCES check_result (id),
            output TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS tool_call (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id TEXT NOT NULL,
            phase TEXT NOT NULL,
            tool TEXT NOT NULL,
            at TEXT NOT NULL
        );
        PRAGMA user_version = 1;
        SQL;

    /**
     * Version 2 (#102): the guard's verb, verdict and rule on each tool call.
     */
    private const string GUARD_COLUMNS = <<<'SQL'
        ALTER TABLE tool_call ADD COLUMN hook TEXT NOT NULL DEFAULT '';
        ALTER TABLE tool_call ADD COLUMN verdict TEXT NOT NULL DEFAULT '';
        ALTER TABLE tool_call ADD COLUMN rule TEXT NOT NULL DEFAULT '';
        PRAGMA user_version = 2;
        SQL;

    /**
     * Version 3 (#103): each filed seeker ledger, and its findings.
     */
    private const string SEEKER_TABLES = <<<'SQL'
        CREATE TABLE IF NOT EXISTS seeker_inspection (
            run_id TEXT NOT NULL,
            round INTEGER NOT NULL,
            phase TEXT NOT NULL,
            inspector TEXT NOT NULL,
            dispatch TEXT NOT NULL,
            counted INTEGER NOT NULL,
            subject_hash TEXT NOT NULL,
            digest TEXT NOT NULL,
            critical INTEGER NOT NULL,
            high INTEGER NOT NULL,
            medium INTEGER NOT NULL,
            low INTEGER NOT NULL,
            holding INTEGER NOT NULL,
            observations INTEGER NOT NULL,
            filed_at TEXT NOT NULL,
            ledger TEXT NOT NULL,
            PRIMARY KEY (run_id, round)
        );
        CREATE TABLE IF NOT EXISTS seeker_finding (
            run_id TEXT NOT NULL,
            round INTEGER NOT NULL,
            ref TEXT NOT NULL,
            severity TEXT NOT NULL,
            item TEXT NOT NULL,
            location TEXT NOT NULL,
            finding TEXT NOT NULL,
            status TEXT NOT NULL,
            reason TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS seeker_finding_by_round ON seeker_finding (run_id, round);
        PRAGMA user_version = 3;
        SQL;

    /**
     * Version 4 (#144): historical captures have unknown completeness; only new full captures assert it.
     */
    private const string TRANSCRIPT_PROVENANCE = <<<'SQL'
        ALTER TABLE check_result ADD COLUMN transcript_complete INTEGER NOT NULL DEFAULT 0 CHECK (transcript_complete IN (0, 1));
        PRAGMA user_version = 4;
        SQL;

    /**
     * @param AtomicFile       $atomicFile       Makes the state directory.
     * @param SqliteStatements $sqliteStatements Runs its statements.
     */
    public function __construct(
        private AtomicFile $atomicFile = new AtomicFile(),
        private SqliteStatements $sqliteStatements = new SqliteStatements(),
    ) {
    }

    /**
     * @param string $directory The state directory.
     *
     * @throws WorkflowRefusal When the store cannot be opened, or a newer engine wrote it.
     *
     * @return PDO The store, at version 4.
     */
    public function open(string $directory): PDO
    {
        $this->atomicFile->directory($directory);

        try {
            $pdo = new PDO('sqlite:' . $directory . '/evidence.sqlite');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $version = (int) $this->statement($pdo, 'PRAGMA user_version', [])->fetchColumn();

            if ($version > self::VERSION) {
                throw new WorkflowRefusal('var/workflow/evidence.sqlite is at version ' . $version . ", past this engine's.");
            }

            $migrations = [1 => self::TABLES, 2 => self::GUARD_COLUMNS, 3 => self::SEEKER_TABLES, 4 => self::TRANSCRIPT_PROVENANCE];

            foreach ($migrations as $step => $sql) {
                $this->migrate($pdo, $version, $step, $sql);
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite could not be opened: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return $pdo;
    }

    /**
     * @param PDO              $pdo        The store.
     * @param string           $sql        A statement.
     * @param list<string|int> $parameters Its values.
     *
     * @throws PDOException When it fails.
     *
     * @return PDOStatement It, executed.
     */
    public function statement(PDO $pdo, string $sql, array $parameters): PDOStatement
    {
        return $this->sqliteStatements->run($pdo, $sql, $parameters);
    }

    /**
     * @param PDO    $pdo     The store.
     * @param int    $version Its version when opened.
     * @param int    $step    A version this engine knows.
     * @param string $sql     The statements that bring the store to it.
     *
     * @throws PDOException When they fail.
     */
    private function migrate(PDO $pdo, int $version, int $step, string $sql): void
    {
        if ($version >= $step) {
            return;
        }

        $pdo->exec($sql);
    }
}
