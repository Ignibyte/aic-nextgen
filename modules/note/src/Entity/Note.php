<?php

declare(strict_types=1);

namespace App\Note\Entity;

/**
 * One note, as a typed row of the `notes` table.
 */
final readonly class Note
{
    /**
     * @param int    $id        The key.
     * @param string $title     The title.
     * @param string $body      The body; empty for none.
     * @param string $createdAt When it was written, as Postgres gives a timestamptz.
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $body,
        public string $createdAt,
    ) {
    }
}
