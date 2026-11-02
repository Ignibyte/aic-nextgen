<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One cell of a `TableRow` (#230): text, or a link when it has an address.
 */
final readonly class TableCell
{
    /**
     * @param string $text   What it shows.
     * @param string $href   Where it links; empty for plain text.
     * @param string $target The link's Tessera target (`@name`); empty for a whole visit.
     */
    public function __construct(
        public string $text,
        public string $href = '',
        public string $target = '',
    ) {
    }
}
