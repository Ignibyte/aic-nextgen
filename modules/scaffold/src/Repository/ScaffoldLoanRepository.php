<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Scaffold\Entity\ScaffoldLoan;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `scaffold_loans` rows over the entity store, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldLoanRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @param int $count How many of the newest rows to read.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<ScaffoldLoan> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $scaffoldLoanRows = $this->entityStore->query($this->scaffoldLoanType())
            ->orderByDescending('id')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::scaffoldLoan(...), array_reverse($scaffoldLoanRows));
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_loans` table.
     */
    private function scaffoldLoanType(): EntityType
    {
        return new EntityType('scaffold_loans', 'id', [
            new Field('book_id', FieldType::Uuid),
            new Field('task_id', FieldType::Reference, nullable: true, references: new EntityType('scaffold_tasks', 'id', [])),
            new Field('borrower', FieldType::Text),
            new Field('lent_at', FieldType::Timestamp),
            new Field('returned_at', FieldType::Timestamp, nullable: true),
        ]);
    }

    /**
     * @param Row $row A `scaffold_loans` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return ScaffoldLoan Its entity.
     */
    private static function scaffoldLoan(Row $row): ScaffoldLoan
    {
        return new ScaffoldLoan(
            $row->string('book_id'),
            $row->intOrNull('task_id'),
            $row->string('borrower'),
            $row->string('lent_at'),
            $row->stringOrNull('returned_at'),
        );
    }
}
