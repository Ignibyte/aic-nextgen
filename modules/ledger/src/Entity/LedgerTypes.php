<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

use AIC\Entity\Domain\EntityEvent;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\Lifecycle;
use AIC\Entity\Domain\Listener;
use InvalidArgumentException;

/**
 * The ledger module's entity types (#310): `ledger_entries`, whose total and
 * label the database generates, and `ledger_reviews`, each referencing its
 * entry; since #309 `ledger_accounts`, audited and soft-deleting, and the
 * `ledger_notices` its listener's job writes.
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
     * The `ledger_accounts` table: audited, soft-deleting, with a listener on each new account (#309).
     */
    private EntityType $accounts;

    /**
     * The `ledger_categories` table: a tree by `parent_id`, with JSON traits (#311).
     */
    private EntityType $categories;

    /**
     * The `ledger_notices` table: what the listener's job wrote.
     */
    private EntityType $notices;

    /**
     * @throws InvalidArgumentException When a type is declared wrongly.
     */
    public function __construct()
    {
        $this->accounts = self::accountsType();
        $this->categories = self::categoriesType();
        $fields = [new Field('line', FieldType::Text), new Field('created_at', FieldType::CreatedAt)];
        $this->notices = new EntityType('ledger_notices', 'id', $fields);
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
     * @return EntityType The `ledger_accounts` table: every write audited, a delete a stamp of `deleted_at`, and a
     *                    new account queues `ledger-account-opened`.
     */
    public function accounts(): EntityType
    {
        return $this->accounts;
    }

    /**
     * @return EntityType The `ledger_categories` table, its `parent_id` a reference to its own table.
     */
    public function categories(): EntityType
    {
        return $this->categories;
    }

    /**
     * @return EntityType The `ledger_notices` table.
     */
    public function notices(): EntityType
    {
        return $this->notices;
    }

    /**
     * @return EntityType The `ledger_reviews` table, its `entry_id` a reference to an entry.
     */
    public function reviews(): EntityType
    {
        return $this->reviews;
    }

    /**
     * @throws InvalidArgumentException When the type is declared wrongly.
     *
     * @return EntityType The `ledger_accounts` table: audited, soft-deleting on `deleted_at`, a listener queuing
     *                    `ledger-account-opened` for each new account, and its standing a state machine (#308).
     */
    private static function accountsType(): EntityType
    {
        $lifecycle = new Lifecycle(true, 'deleted_at', [new Listener(EntityEvent::Created, 'ledger-account-opened')]);

        return new EntityType('ledger_accounts', 'id', [
            new Field('name', FieldType::Text),
            new Field('deleted_at', FieldType::Timestamp, true),
            new Field('created_at', FieldType::CreatedAt),
            new Field('standing', FieldType::Choice, false, null, ['open', 'frozen', 'closed']),
        ], lifecycle: $lifecycle, stateMachine: (new LedgerAccountStandingTransitions())->machine());
    }

    /**
     * @throws InvalidArgumentException When the type is declared wrongly.
     *
     * @return EntityType The `ledger_categories` table: a name, the parent it sits under and JSON traits. The parent's
     *                    type is built without the reference, as a target always is.
     */
    private static function categoriesType(): EntityType
    {
        $name = new Field('name', FieldType::Text);
        $traits = new Field('traits', FieldType::Json);
        $created = new Field('created_at', FieldType::CreatedAt);
        $entityType = new EntityType('ledger_categories', 'id', [$name, $traits, $created]);

        return new EntityType('ledger_categories', 'id', [
            $name,
            new Field('parent_id', FieldType::Reference, true, $entityType),
            $traits,
            $created,
        ]);
    }
}
