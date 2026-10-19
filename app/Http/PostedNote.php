<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A posted note form: its title and body, trimmed, and why the title is
 * refused.
 */
final readonly class PostedNote
{
    /**
     * @param string $title The title.
     * @param string $body  The body; empty for none.
     * @param string $error Why the title is refused; empty when it is fine.
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $error,
    ) {
    }
}
