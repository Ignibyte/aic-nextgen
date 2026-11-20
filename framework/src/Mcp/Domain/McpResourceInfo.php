<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * One resource as `resources/list` shows it (#316), with the permission a
 * caller needs, from its `#[ResourceInfo]`.
 */
final readonly class McpResourceInfo implements JsonSerializable
{
    /**
     * @param string $uri         The resource's URI.
     * @param string $name        Its short name.
     * @param string $description What it holds.
     * @param string $mimeType    Its text's media type.
     * @param string $needs       The permission a caller needs.
     */
    public function __construct(
        public string $uri,
        public string $name,
        public string $description,
        public string $mimeType,
        public string $needs,
    ) {
    }

    /**
     * @return array<string, string> The resource as MCP lists one.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['uri' => $this->uri, 'name' => $this->name, 'description' => $this->description, 'mimeType' => $this->mimeType];
    }
}
