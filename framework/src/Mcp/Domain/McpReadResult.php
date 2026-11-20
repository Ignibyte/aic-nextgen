<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * The answer to `resources/read` (#316): the resource's text, with its URI and
 * media type.
 */
final readonly class McpReadResult implements JsonSerializable
{
    /**
     * @param McpResourceInfo $mcpResourceInfo The resource.
     * @param string          $text            Its text.
     */
    public function __construct(
        private McpResourceInfo $mcpResourceInfo,
        private string $text,
    ) {
    }

    /**
     * @return array{contents: list<array{uri: string, mimeType: string, text: string}>} The result.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['contents' => [[
            'mimeType' => $this->mcpResourceInfo->mimeType,
            'text' => $this->text,
            'uri' => $this->mcpResourceInfo->uri,
        ]]];
    }
}
