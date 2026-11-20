<?php

declare(strict_types=1);

namespace AIC\Mcp\Attribute;

use Attribute;

/**
 * Names and describes an `McpResource` (#316). The build reads it; it does
 * nothing at run time.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ResourceInfo
{
    /**
     * @param string $uri         The resource's URI (`ledger://accounts`), unique in the app.
     * @param string $name        Its short name.
     * @param string $description What it holds.
     * @param string $needs       The permission a caller needs to see and read it, declared by some module.
     * @param string $mimeType    The media type of the text `read()` returns.
     */
    public function __construct(
        public string $uri,
        public string $name,
        public string $description,
        public string $needs,
        public string $mimeType = 'text/plain',
    ) {
    }
}
