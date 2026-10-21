<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Tessera\Domain\RecordedWrite;

/**
 * The write ids seen, and their answers (#72): `aic_tessera_writes`, rows
 * kept ten minutes (the protocol asks for at least five) and purged as new
 * writes are claimed.
 */
final readonly class TesseraWrites
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $scope   The visitor's scope.
     * @param string $writeId The write's id.
     *
     * @throws QueryException When a statement fails.
     *
     * @return bool Whether this is the first time the scope sent the id, which now runs.
     */
    public function claim(string $scope, string $writeId): bool
    {
        $this->database->execute("DELETE FROM aic_tessera_writes WHERE created_at < now() - interval '10 minutes'", []);

        return $this->database->execute(
            "INSERT INTO aic_tessera_writes (scope, write_id, status, headers, body, created_at) VALUES (?, ?, 0, '', '', now())"
            . ' ON CONFLICT (scope, write_id) DO NOTHING',
            [$scope, $writeId],
        ) === 1;
    }

    /**
     * @param string        $scope         The visitor's scope.
     * @param string        $writeId       The write's id.
     * @param RecordedWrite $recordedWrite Its answer.
     *
     * @throws QueryException When the statement fails.
     */
    public function record(string $scope, string $writeId, RecordedWrite $recordedWrite): void
    {
        $this->database->execute(
            'UPDATE aic_tessera_writes SET status = ?, headers = ?, body = ? WHERE scope = ? AND write_id = ?',
            [$recordedWrite->status, $recordedWrite->headers, $recordedWrite->body, $scope, $writeId],
        );
    }

    /**
     * @param string $scope   The visitor's scope.
     * @param string $writeId The write's id.
     *
     * @throws QueryException When the query fails.
     *
     * @return RecordedWrite Its answer; unsettled when it never settled or is gone.
     */
    public function answer(string $scope, string $writeId): RecordedWrite
    {
        $row = $this->database->fetchOne(
            "SELECT coalesce(max(status), 0) AS status, coalesce(max(headers), '') AS headers, coalesce(max(body), '') AS body"
            . ' FROM aic_tessera_writes WHERE scope = ? AND write_id = ?',
            [$scope, $writeId],
        );

        return new RecordedWrite($row->int('status'), $row->string('headers'), $row->string('body'));
    }
}
