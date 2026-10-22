<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * How the site answered a GET the rendered check sent (#101): the status and
 * the content type, without the body.
 */
final readonly class RenderedAnswer
{
    /**
     * @param int    $status      The status code.
     * @param string $contentType The `Content-Type` header, lowercased; empty when absent.
     */
    public function __construct(
        public int $status,
        public string $contentType,
    ) {
    }
}
