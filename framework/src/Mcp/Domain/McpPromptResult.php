<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Mcp\Contracts\McpMessage;
use JsonSerializable;
use Override;

/**
 * The answer to `prompts/get` (#316): the prompt's description and the
 * messages it wrote from the arguments.
 */
final readonly class McpPromptResult implements JsonSerializable
{
    /**
     * @param string           $description The prompt's description.
     * @param list<McpMessage> $messages    Its messages.
     */
    public function __construct(
        private string $description,
        private array $messages,
    ) {
    }

    /**
     * @return array{description: string, messages: list<McpMessage>} The result.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['description' => $this->description, 'messages' => $this->messages];
    }
}
