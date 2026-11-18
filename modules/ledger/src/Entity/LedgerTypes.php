<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The ledger module's entity types (#310): `ledger_entries`, whose total and
 * label the database generates, and `ledger_reviews`, each referencing its
 * entry.
 */
final readonly class LedgerTypes
{
    /**
     * The `ledger_entries` table: append-only.
     */
    private EntityType $entries;

    /**
     * The `ledger_reviews` table: its decision and reviewer final once set.
     */
    private EntityType $reviews;

    /**
     * @throws InvalidArgumentException When a type is declared wrongly.
     */
    public function __construct()
    {
        $this->entries = new EntityType('ledger_entries', 'id', [
            new Field('account', FieldType::Text),
            new Field('quantity', FieldType::Integer),
            new Field('unit_price', FieldType::Numeric),
            new Field('memo', FieldType::Text),
            new Field('total', FieldType::Numeric),
            new Field('label', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
        $this->reviews = new EntityType('ledger_reviews', 'id', [
            new Field('entry_id', FieldType::Reference, false, $this->entries),
            new Field('decision', FieldType::Choice, true, null, ['approved', 'rejected']),
            new Field('reviewer', FieldType::Text, true),
            new Field('escalated', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @return EntityType The `ledger_entries` table. Its `total` and `label` are generated: read them, never write
     *                    them.
     */
    public function entries(): EntityType
    {
        return $this->entries;
    }

    /**
     * @return EntityType The `ledger_reviews` table, its `entry_id` a reference to an entry.
     */
    public function reviews(): EntityType
    {
        return $this->reviews;
    }
}
