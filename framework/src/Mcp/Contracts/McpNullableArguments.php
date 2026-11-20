<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

/**
 * A call's arguments read as nullable (#316), for an input whose parameter
 * allows null: a field that is present and null reads as null; one that is
 * missing is still refused, unless a default filled it.
 *
 * @api
 */
final readonly class McpNullableArguments
{
    /**
     * @param McpArguments $mcpArguments The arguments.
     */
    public function __construct(
        private McpArguments $mcpArguments,
    ) {
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing, or neither null nor a string.
     *
     * @return string|null Its value.
     */
    public function string(string $name): ?string
    {
        return $this->mcpArguments->isNull($name)
            ? null
            : $this->mcpArguments->string($name);
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing, or neither null nor an integer.
     *
     * @return int|null Its value.
     */
    public function int(string $name): ?int
    {
        return $this->mcpArguments->isNull($name)
            ? null
            : $this->mcpArguments->int($name);
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing, or neither null nor a number.
     *
     * @return float|null Its value.
     */
    public function float(string $name): ?float
    {
        return $this->mcpArguments->isNull($name)
            ? null
            : $this->mcpArguments->float($name);
    }

    /**
     * @param string $name A field's name.
     *
     * @throws McpArgumentException When it is missing, or neither null nor true or false.
     *
     * @return bool|null Its value.
     */
    public function bool(string $name): ?bool
    {
        return $this->mcpArguments->isNull($name)
            ? null
            : $this->mcpArguments->bool($name);
    }
}
