<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * The answer to a list method (#316): `tools`, `resources` or `prompts`, each
 * the caller may use. Every list is whole, so there is no cursor.
 */
final readonly class McpListResult implements JsonSerializable
{
    /**
     * @param string                 $key   The list's key in the result.
     * @param list<JsonSerializable> $items What it holds.
     */
    public function __construct(
        private string $key,
        private array $items,
    ) {
    }

    /**
     * @return array<string, list<JsonSerializable>> The result.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [$this->key => $this->items];
    }
}
