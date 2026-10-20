<?php

declare(strict_types=1);

namespace App\Content;

use function array_filter;
use function array_map;
use function array_values;
use function preg_split;
use function trim;

/**
 * One content page (#50): text stored by its slug.
 */
final readonly class ContentPage
{
    /**
     * @param string $slug  Its key and path, lower-case words joined by hyphens.
     * @param string $title Its title.
     * @param string $body  Its text; paragraphs are separated by blank lines.
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $body,
    ) {
    }

    /**
     * @return list<string> Its paragraphs, trimmed, empty ones left out.
     */
    public function paragraphs(): array
    {
        $parts = preg_split('/\\R\\s*\\R/', $this->body);

        return array_values(array_filter(
            array_map(trim(...), $parts === false ? [] : $parts),
            static fn (string $paragraph): bool => $paragraph !== '',
        ));
    }
}
