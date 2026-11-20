<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One field of an MCP tool's input or output (#316): a constructor parameter's
 * name, type, `@param` description and default.
 */
final readonly class McpField
{
    /**
     * @param string  $name        The parameter's name, the field's JSON key.
     * @param McpType $mcpType     Its type.
     * @param string  $description Its `@param` description.
     * @param string  $default     Its default as JSON (`2`, `"usd"`, `[]`, `null`); empty when it has none.
     */
    public function __construct(
        public string $name,
        public McpType $mcpType,
        public string $description,
        public string $default = '',
    ) {
    }

    /**
     * @return bool Whether the parameter has a default, so the field is optional.
     */
    public function defaulted(): bool
    {
        return $this->default !== '';
    }
}
