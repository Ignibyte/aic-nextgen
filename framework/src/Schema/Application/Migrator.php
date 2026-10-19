<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Schema\Contracts\Migration;

use function array_key_exists;
use function hash;
use function implode;

/**
 * Runs the app's migrations against the database and keeps the ledger,
 * `aic_migrations`: each applied version with a sha256 checksum of its
 * compiled statements and when it was applied. A migration runs in one
 * transaction with its ledger row. An applied migration whose statements
 * have since changed reads as changed.
 */
final readonly class Migrator
{
    private const string LEDGER = 'aic_migrations';

    /**
     * @param Database     $database    The database.
     * @param PostgresDdl  $postgresDdl The dialect.
     * @param list<object> $migrations  The Migrations, in the order they apply.
     */
    public function __construct(
        private Database $database,
        private PostgresDdl $postgresDdl,
        private array $migrations,
    ) {
    }

    /**
     * @throws QueryException When the ledger cannot be read or made, or a migration fails (it is rolled back).
     *
     * @return list<string> The versions applied now, in order.
     */
    public function migrate(): array
    {
        $this->provisionLedger();
        $applied = $this->applied();
        $versions = [];

        foreach ($this->migrations as $migration) {
            if (!$migration instanceof Migration) {
                continue;
            }

            if (array_key_exists($migration->version(), $applied)) {
                continue;
            }

            $this->apply($migration);
            $versions[] = $migration->version();
        }

        return $versions;
    }

    /**
     * @throws QueryException When the ledger cannot be read.
     *
     * @return array<string, string> Each migration's state by version: applied, pending or changed.
     */
    public function status(): array
    {
        $applied = $this->applied();
        $states = [];

        foreach ($this->migrations as $migration) {
            if (!$migration instanceof Migration) {
                continue;
            }

            $version = $migration->version();
            $states[$version] = self::state($applied[$version] ?? '', self::checksum($this->statements($migration)));
        }

        return $states;
    }

    /**
     * @param Migration $migration A pending migration.
     *
     * @throws QueryException When it fails; it is rolled back first.
     */
    private function apply(Migration $migration): void
    {
        $statements = $this->statements($migration);
        $this->database->beginTransaction();

        try {
            $this->executeAll($statements);
            $this->database->execute(
                'INSERT INTO ' . self::LEDGER . ' (version, checksum, applied_at) VALUES (?, ?, now())',
                [$migration->version(), self::checksum($statements)],
            );
            $this->database->commit();
        } catch (QueryException $queryException) {
            $this->database->rollBack();

            throw new QueryException(
                'Migration ' . $migration->version() . ' failed: ' . $queryException->getMessage(),
                0,
                $queryException,
            );
        }
    }

    /**
     * @param Migration $migration A migration.
     *
     * @return list<string> Its statements, compiled.
     */
    private function statements(Migration $migration): array
    {
        $schemaPlan = new SchemaPlan();
        $migration->describe($schemaPlan);

        return $schemaPlan->statements($this->postgresDdl);
    }

    /**
     * @throws QueryException When the ledger cannot be looked for or made.
     */
    private function provisionLedger(): void
    {
        if ($this->ledgerExists()) {
            return;
        }

        $schemaPlan = new SchemaPlan();
        $schemaPlan->create(self::LEDGER)
            ->string('version', 200)
            ->string('checksum', 64)
            ->timestampTz('applied_at')
            ->primary(['version']);
        $this->executeAll($schemaPlan->statements($this->postgresDdl));
    }

    /**
     * @param list<string> $statements Statements with no placeholders.
     *
     * @throws QueryException When one fails.
     */
    private function executeAll(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->database->execute($statement, []);
        }
    }

    /**
     * @throws QueryException When the catalogue cannot be read.
     *
     * @return bool Whether the ledger table exists.
     */
    private function ledgerExists(): bool
    {
        return $this->database->fetchOne("SELECT coalesce(to_regclass(?)::text, '') AS ledger", [self::LEDGER])
            ->string('ledger') !== '';
    }

    /**
     * @throws QueryException When the ledger cannot be read.
     *
     * @return array<string, string> Each applied version's checksum; empty without a ledger.
     */
    private function applied(): array
    {
        if (!$this->ledgerExists()) {
            return [];
        }

        $applied = [];

        foreach ($this->database->fetchAll('SELECT version, checksum FROM ' . self::LEDGER, []) as $row) {
            $applied[$row->string('version')] = $row->string('checksum');
        }

        return $applied;
    }

    /**
     * @param string $stored  The checksum in the ledger; empty when the migration is not applied.
     * @param string $current The checksum of its statements now.
     *
     * @return string applied, pending or changed.
     */
    private static function state(string $stored, string $current): string
    {
        if ($stored === '') {
            return 'pending';
        }

        return $stored === $current
            ? 'applied'
            : 'changed';
    }

    /**
     * @param list<string> $statements A migration's statements.
     *
     * @return string Their sha256, hex.
     */
    private static function checksum(array $statements): string
    {
        return hash('sha256', implode(";\n", $statements));
    }
}
