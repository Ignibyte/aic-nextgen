<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

/**
 * What an MCP server offers (#316): the adapters of a module's tools,
 * resources and prompts, as `App\Generated\<Module>Mcp::catalog()` gives them
 * from its `src/Mcp/`, or several modules' joined with `with()`. The server
 * finds each call's entry here; permissions are the server's to check.
 *
 * @api
 */
final readonly class McpCatalog
{
    /**
     * @param list<McpToolCall>     $tools     The tools, in build order.
     * @param list<McpResourceRead> $resources The resources.
     * @param list<McpPromptGet>    $prompts   The prompts.
     */
    public function __construct(
        public array $tools = [],
        public array $resources = [],
        public array $prompts = [],
    ) {
    }

    /**
     * @return list<McpToolInfo> Each tool's descriptor.
     */
    public function toolInfos(): array
    {
        $infos = [];

        foreach ($this->tools as $tool) {
            $infos[] = $tool->toolInfo();
        }

        return $infos;
    }

    /**
     * @return list<McpResourceInfo> Each resource's descriptor.
     */
    public function resourceInfos(): array
    {
        $infos = [];

        foreach ($this->resources as $resource) {
            $infos[] = $resource->resourceInfo();
        }

        return $infos;
    }

    /**
     * @return list<McpPromptInfo> Each prompt's descriptor.
     */
    public function promptInfos(): array
    {
        $infos = [];

        foreach ($this->prompts as $prompt) {
            $infos[] = $prompt->promptInfo();
        }

        return $infos;
    }

    /**
     * @param McpCatalog $mcpCatalog Another module's catalog.
     *
     * @return McpCatalog Both, this one's entries first.
     */
    public function with(self $mcpCatalog): self
    {
        return new self(
            [...$this->tools, ...$mcpCatalog->tools],
            [...$this->resources, ...$mcpCatalog->resources],
            [...$this->prompts, ...$mcpCatalog->prompts],
        );
    }
}
