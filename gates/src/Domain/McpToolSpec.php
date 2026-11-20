<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Mcp\Attribute\ToolInfo;

/**
 * One MCP tool as the build read it (#316): its class, its `#[ToolInfo]`, its
 * input and output types and whether its `call()` takes the caller.
 */
final readonly class McpToolSpec
{
    /**
     * @param class-string $class     The tool's class.
     * @param ToolInfo     $toolInfo  Its attribute.
     * @param McpType      $input     Its input class, as an object type.
     * @param McpType      $output    Its output class, as an object type.
     * @param bool         $withActor Whether `call()` takes the `Actor` second.
     * @param list<string> $throws    The exceptions its `call()` says it throws, by class.
     */
    public function __construct(
        public string $class,
        public ToolInfo $toolInfo,
        public McpType $input,
        public McpType $output,
        public bool $withActor,
        public array $throws = [],
    ) {
    }
}
