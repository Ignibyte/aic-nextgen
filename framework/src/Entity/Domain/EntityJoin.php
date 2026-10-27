<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * One join of a query (#190): the reference field it follows, the entity type
 * that field points to, the prefix that names the joined table and its
 * columns (`note` gives `note.title`, read back as `note_title`), and whether
 * a row without a match stays (a left join).
 */
final readonly class EntityJoin
{
    /**
     * @param Field      $field      The reference field followed.
     * @param EntityType $entityType The entity type it points to.
     * @param string     $prefix     The joined table's alias, an identifier.
     * @param bool       $optional   Whether a row without a match stays, its joined columns null.
     */
    public function __construct(
        public Field $field,
        public EntityType $entityType,
        public string $prefix,
        public bool $optional,
    ) {
    }
}
