<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * One tool as `tools/list` shows it (#316), with the permission a caller needs,
 * as the build wrote it from `#[ToolInfo]` and the tool's `call()` signature.
 */
final readonly class McpToolInfo implements JsonSerializable
{
    /**
     * @param string     $name        The tool's name.
     * @param string     $description What it does.
     * @param string     $needs       The permission a caller needs.
     * @param JsonSchema $input       Its arguments' schema.
     * @param JsonSchema $output      Its result's schema.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $needs,
        public JsonSchema $input,
        public JsonSchema $output,
    ) {
    }

    /**
     * @return array<string, string|JsonSchema> The tool as MCP lists one.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'inputSchema' => $this->input,
            'name' => $this->name,
            'outputSchema' => $this->output,
        ];
    }
}
