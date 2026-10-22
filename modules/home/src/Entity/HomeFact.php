<?php

declare(strict_types=1);

namespace App\Home\Entity;

/**
 * One fact about the request the home page serves.
 */
final readonly class HomeFact
{
    /**
     * @param string $id    The value's element id.
     * @param string $term  What it is.
     * @param string $value The value.
     */
    public function __construct(
        public string $id,
        public string $term,
        public string $value,
    ) {
    }
}
