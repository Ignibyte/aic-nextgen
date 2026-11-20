<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

use function json_encode;

/**
 * The answer to `tools/call` (#316): the tool's output as structured content
 * and, for a client that reads only text, the same JSON as text; or, when the
 * tool refused, its reason as text with `isError`.
 */
final readonly class McpToolResult implements JsonSerializable
{
    /**
     * @param JsonSerializable|null $jsonSerializable The tool's output; null when it refused.
     * @param string                $refusal          Why it refused.
     */
    public function __construct(
        private ?JsonSerializable $jsonSerializable,
        private string $refusal = '',
    ) {
    }

    /**
     * @return array<string, bool|JsonSerializable|list<array{type: string, text: string}>> The result.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        if (!$this->jsonSerializable instanceof JsonSerializable) {
            return ['content' => [['type' => 'text', 'text' => $this->refusal]], 'isError' => true];
        }

        return [
            'content' => [['type' => 'text', 'text' => (string) json_encode($this->jsonSerializable)]],
            'isError' => false,
            'structuredContent' => $this->jsonSerializable,
        ];
    }
}
