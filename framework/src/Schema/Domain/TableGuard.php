<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

/**
 * A database guard on a table (#310): writes it refuses, held by a trigger the
 * dialect generates, so it holds against every writer and every race.
 */
final readonly class TableGuard
{
    /**
     * @param GuardKind        $guardKind What it refuses.
     * @param Identifier       $table     The table.
     * @param list<Identifier> $columns   A FinalOnce guard's columns; none for AppendOnly.
     *
     * @throws InvalidArgumentException When a FinalOnce guard has no columns, or an AppendOnly guard has some.
     */
    public function __construct(
        public GuardKind $guardKind,
        public Identifier $table,
        public array $columns,
    ) {
        if (($guardKind === GuardKind::FinalOnce) === ($columns === [])) {
            throw new InvalidArgumentException(
                'Table ' . $table->value . ': a final-once guard names its columns, and an append-only guard none.',
            );
        }
    }
}
