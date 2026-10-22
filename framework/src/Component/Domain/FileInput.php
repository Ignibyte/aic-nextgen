<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A file chooser (#44), for a form with multipart encoding.
 */
final readonly class FileInput implements Component
{
    /**
     * @param string $id     The element id, which its label names.
     * @param string $name   The field name.
     * @param string $accept The media types to offer, comma-separated; a hint only, the server checks.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $accept,
    ) {
    }
}
