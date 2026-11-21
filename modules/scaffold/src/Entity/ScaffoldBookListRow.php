<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

/**
 * One `scaffold_books` row as GET /scaffold/books lists it, made by
 * bin/aic make:list-page (#324).
 */
final readonly class ScaffoldBookListRow
{
    /**
     * @param string $title   The title.
     * @param int    $pages   The pages.
     * @param string $price   The price.
     * @param bool   $retired The retired.
     */
    public function __construct(
        public string $title,
        public int $pages,
        public string $price,
        public bool $retired,
    ) {
    }
}
