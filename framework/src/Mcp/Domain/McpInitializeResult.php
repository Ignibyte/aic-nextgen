<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * The answer to `initialize` (#316): the protocol version both sides speak, the
 * server's tools, resources and prompts (whose lists never change while it
 * runs) and its name.
 */
final readonly class McpInitializeResult implements JsonSerializable
{
    /**
     * @param string $protocolVersion The version agreed.
     * @param string $name            The app's name.
     */
    public function __construct(
        private string $protocolVersion,
        private string $name,
    ) {
    }

    /**
     * @return array<string, string|array<string, string|array<string, bool>>> The result.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $fixed = ['listChanged' => false];

        return [
            'capabilities' => ['tools' => $fixed, 'resources' => $fixed, 'prompts' => $fixed],
            'protocolVersion' => $this->protocolVersion,
            'serverInfo' => ['name' => $this->name, 'version' => '1.0.0'],
        ];
    }
}
