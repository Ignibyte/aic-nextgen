<?php

declare(strict_types=1);

namespace App\Site\Entity;

/**
 * One demo the home page links to.
 */
final readonly class HomeDemo
{
    /**
     * @param string $path  Its path.
     * @param string $label The link's text.
     * @param bool   $plain Whether the link loads in full, past Tessera (`data-enhance="off"`): not a page.
     */
    public function __construct(
        public string $path,
        public string $label,
        public bool $plain,
    ) {
    }
}
