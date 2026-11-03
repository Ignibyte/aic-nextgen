<?php

declare(strict_types=1);

namespace App\Theme\Components\Home;

/**
 * One demo the home page links to.
 */
final readonly class HomeDemo
{
    /**
     * @param string $path       Its path.
     * @param string $label      The link's text.
     * @param bool   $plain      Whether the link loads in full, past Tessera (`data-enhance="off"`): not a page.
     * @param bool   $prefetched Whether Tessera prefetches it on hover (`data-prefetch="hover"`, #254).
     */
    public function __construct(
        public string $path,
        public string $label,
        public bool $plain,
        public bool $prefetched = false,
    ) {
    }
}
