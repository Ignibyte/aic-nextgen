<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use PDO;
use PDOException;
use PDOStatement;

/**
 * The evidence store's file and schema (#101): `var/workflow/evidence.sqlite`
 * at version 2 (#102 added the guard's columns), forward-only. A store from a
 * newer engine is refused rather than read wrong; #103 adds its tables by
 * migrating it. Its statements are prepared and executed here, failing
 * loudly.
 */
final readonly class EvidenceSchema
{
    /**
     * The version this engine writes and reads.
     */
    private const int VERSION = 2;

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
     * @param AtomicFile $atomicFile Makes the state directory.
     */
    public function __construct(
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param string $directory The state directory.
     *
     * @throws WorkflowRefusal When the store cannot be opened, or a newer engine wrote it.
     *
     * @return PDO The store, at version 2.
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

            if ($version < 1) {
                $pdo->exec(self::TABLES);
            }

            if ($version < 2) {
                $pdo->exec(self::GUARD_COLUMNS);
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
        $statement = $pdo->prepare($sql);

        if (!$statement instanceof PDOStatement || !$statement->execute($parameters)) {
            throw new PDOException('the statement failed: ' . $sql);
        }

        return $statement;
    }
}
