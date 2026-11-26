<?php

declare(strict_types=1);

namespace {namespace}\Mcp;

use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use Override;

/**
 * An MCP resource, made by bin/aic make:mcp-resource (#334). The build writes
 * its adapter into the module's generated catalog, which the module's MCP
 * endpoint serves; an agent lists it and reads it by its uri.
 */
#[ResourceInfo(
    uri: '{uri}',
    name: '{name}',
    description: 'Say what the resource holds and when an agent should read it.',
    needs: '{permission}',
    mimeType: 'text/markdown',
)]
final readonly class {class} implements McpResource
{
    /**
     * @return string The resource's text.
     */
    #[Override]
    public function read(): string
    {
        return "# {name}\n\nWrite what an agent should read here.\n";
    }
}
