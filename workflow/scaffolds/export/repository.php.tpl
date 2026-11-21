<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use AIC\Queue\Application\PostgresQueue;
use InvalidArgumentException;
use JsonException;

use function preg_match;

/**
 * The exports of `{table}`, made by bin/aic make:export (#323): each
 * a row keyed by a uuid the database makes, which names its file in the
 * private files and its download. The view `{states}` says
 * whether one is past its {days} days by the database's clock, so no PHP
 * works out a time.
 */
final readonly class {class}
{
    /**
     * The job that writes an export's file.
     */
    public const string JOB = '{job}';

    /**
     * Where the files are kept, in the private files.
     */
    private const string FOLDER = '{folder}';

    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param PostgresQueue $postgresQueue Queues the job that writes an export.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the table's type is malformed.
     * @throws JsonException            When the job's payload cannot be encoded.
     * @throws QueryException           When the export cannot be stored or its job queued.
     *
     * @return string The new export's id; its job is queued.
     */
    public function queue(): string
    {
        $id = $this->entityStore->insertUuid($this->exports(), []);
        $this->postgresQueue->dispatch(self::JOB, ['export' => $id], 0);

        return $id;
    }

    /**
     * @param string $id What names an export: a route's parameter or a job's payload.
     *
     * @throws InvalidArgumentException When the view's type is malformed.
     * @throws QueryException           When the query fails.
     *
     * @return bool Whether it is an export within its days.
     */
    public function live(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $id) === 1
            && $this->entityStore->query($this->states())->where('id', $id)->where('expired', false)->first() !== null;
    }

    /**
     * @throws InvalidArgumentException When the view's type is malformed.
     * @throws QueryException           When the query fails.
     *
     * @return list<string> The exports past their days, by id.
     */
    public function expired(): array
    {
        $ids = [];

        foreach ($this->entityStore->query($this->states())->where('expired', true)->rows() as $row) {
            $ids[] = $row->string('id');
        }

        return $ids;
    }

    /**
     * @param string $id An export's id.
     *
     * @throws InvalidArgumentException When the table's type is malformed.
     * @throws EntityNotFoundException  When the export is gone already.
     * @throws QueryException           When the delete fails.
     */
    public function delete(string $id): void
    {
        $this->entityStore->delete($this->exports(), $id);
    }

    /**
     * @param string $id An export's id.
     *
     * @return string Its file's key in the private files.
     */
    public function key(string $id): string
    {
        return self::FOLDER . $id . '.csv';
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{table}` table: the uuid key and `created_at`, set at insert.
     */
    private function exports(): EntityType
    {
        return new EntityType('{table}', 'id', [
            new Field('created_at', FieldType::CreatedAt),
        ], keyType: FieldType::Uuid);
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{states}` view: each export and whether it is past its days.
     */
    private function states(): EntityType
    {
        return new EntityType('{states}', 'id', [
            new Field('expired', FieldType::Boolean),
        ], true, FieldType::Uuid);
    }
}
