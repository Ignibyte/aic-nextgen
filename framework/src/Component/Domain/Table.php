<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A table of text (#230): a head of column names and rows of cells, each cell
 * text or a link. With `poll` set, Tessera refreshes it from that URL every
 * five seconds.
 */
final readonly class Table implements Component
{
    /**
     * @param list<string>   $head    The column names.
     * @param list<TableRow> $rows    The rows, in order.
     * @param string         $caption What the table shows; empty for none.
     * @param string         $poll    The same-origin URL Tessera refreshes it from every five seconds; empty for none.
     */
    public function __construct(
        public array $head,
        public array $rows,
        public string $caption = '',
        public string $poll = '',
    ) {
    }
}
