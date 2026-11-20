<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Attribute\ResourceInfo;

/**
 * One module's MCP entries as the build read them from its `src/Mcp/` (#316):
 * its tools, and its resources and prompts by class with their attributes.
 */
final readonly class McpModuleSpec
{
    /**
     * @param ModuleDefinition                  $moduleDefinition The module.
     * @param list<McpToolSpec>                 $tools            Its tools, in file name order.
     * @param array<class-string, ResourceInfo> $resources        Its resources, by class.
     * @param array<class-string, PromptInfo>   $prompts          Its prompts, by class.
     */
    public function __construct(
        public ModuleDefinition $moduleDefinition,
        public array $tools = [],
        public array $resources = [],
        public array $prompts = [],
    ) {
    }

    /**
     * @return bool Whether it has no entry, so no catalog is written for it.
     */
    public function empty(): bool
    {
        return $this->tools === [] && $this->resources === [] && $this->prompts === [];
    }
}
