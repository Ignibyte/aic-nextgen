<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\EntityEvent;
use AIC\Entity\Domain\EntityType;
use AIC\Queue\Application\PostgresQueue;
use JsonException;

use function array_merge;
use function implode;

/**
 * Runs a write to an observed entity type and records its event (#309), in
 * the write's transaction: the caller's when one is open, its own otherwise.
 * For each row the statement returns (`aic_key`, the key as text) it writes
 * an audit row when the type is audited and queues each listener's job. A
 * type that is not observed only runs the statement.
 */
final readonly class EntityLifecycle
{
    /**
     * @param Database $database The database, with the audit table (CreateEntityAuditTable) and the job tables.
     * @param string   $actor    Who writes: the signed-in user's id, empty for the app itself.
     */
    public function __construct(
        private Database $database,
        private string $actor,
    ) {
    }

    /**
     * @param EntityType                       $entityType The entity type written.
     * @param string                           $sql        An INSERT, returning the key as `aic_key`.
     * @param list<string|int|float|bool|null> $params     The values it writes, in order.
     * @param list<string>                     $columns    The fields it writes, in the same order.
     *
     * @throws QueryException When the insert, the audit or a job fails; its own transaction is rolled back.
     *
     * @return Rows The new row.
     */
    public function insert(EntityType $entityType, string $sql, array $params, array $columns): Rows
    {
        return $this->write($entityType, EntityEvent::Created, $sql, $params, $columns, $params);
    }

    /**
     * @param EntityType                       $entityType  The entity type written.
     * @param EntityEvent                      $entityEvent What the statement does.
     * @param string                           $sql         The statement, returning each row's key as `aic_key`.
     * @param list<string|int|float|bool|null> $params      Its values, in order.
     * @param list<string>                     $columns     The fields it writes, for the audit and the payload.
     * @param list<string|int|float|bool|null> $values      Their values, in the same order.
     *
     * @throws QueryException When the statement, the audit or a job fails; its own transaction is rolled back.
     *
     * @return Rows The rows the statement returned.
     */
    public function write(
        EntityType $entityType,
        EntityEvent $entityEvent,
        string $sql,
        array $params,
        array $columns,
        array $values,
    ): Rows {
        if (!$entityType->lifecycle->isObserved()) {
            return $this->database->fetchAll($sql, $params);
        }

        $entityWrite = new EntityWrite($entityType, $entityEvent, $columns, $values);

        return $this->database->inTransaction()
            ? $this->recorded($entityWrite, $sql, $params)
            : $this->transacted($entityWrite, $sql, $params);
    }

    /**
     * @param EntityWrite                      $entityWrite What is written.
     * @param string                           $sql         The statement.
     * @param list<string|int|float|bool|null> $params      Its values.
     *
     * @throws QueryException When the statement, the audit or a job fails.
     *
     * @return Rows The rows the statement returned, committed with their record.
     */
    private function transacted(EntityWrite $entityWrite, string $sql, array $params): Rows
    {
        $this->database->beginTransaction();
        $committed = false;

        try {
            $rows = $this->recorded($entityWrite, $sql, $params);
            $this->database->commit();
            $committed = true;
        } finally {
            if (!$committed && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
        }

        return $rows;
    }

    /**
     * @param EntityWrite                      $entityWrite What is written.
     * @param string                           $sql         The statement.
     * @param list<string|int|float|bool|null> $params      Its values.
     *
     * @throws QueryException When the statement, the audit or a job fails.
     *
     * @return Rows The rows the statement returned.
     */
    private function recorded(EntityWrite $entityWrite, string $sql, array $params): Rows
    {
        $rows = $this->database->fetchAll($sql, $params);
        $postgresQueue = new PostgresQueue($this->database);
        $entityType = $entityWrite->entityType;
        $event = $entityWrite->entityEvent->value;

        foreach ($rows as $row) {
            $key = $row->string('aic_key');

            if ($entityType->lifecycle->audited) {
                $params = array_merge([$entityType->table, $key, $event, $this->actor], $entityWrite->values);
                $this->database->execute($entityWrite->auditSql(), $params);
            }

            $this->queue($postgresQueue, $entityWrite, $key);
        }

        return $rows;
    }

    /**
     * @param PostgresQueue $postgresQueue The queue, on the write's connection.
     * @param EntityWrite   $entityWrite   What is written.
     * @param string        $key           The row's key, as text.
     *
     * @throws QueryException When a job cannot be queued, or its payload encoded.
     */
    private function queue(PostgresQueue $postgresQueue, EntityWrite $entityWrite, string $key): void
    {
        $entityType = $entityWrite->entityType;
        $payload = [
            'entity' => $entityType->table,
            'event' => $entityWrite->entityEvent->value,
            'fields' => implode(',', $entityWrite->columns),
            'key' => $key,
        ];

        foreach ($entityType->lifecycle->jobs($entityWrite->entityEvent) as $job) {
            try {
                $postgresQueue->dispatch($job, $payload, 0);
            } catch (JsonException $jsonException) {
                throw new QueryException('The ' . $job . " job's payload could not be encoded.", 0, $jsonException);
            }
        }
    }
}
