<?php

declare(strict_types=1);

namespace App\Scaffold\Mcp;

use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use Override;

/**
 * An MCP resource, made by bin/aic make:mcp-resource (#334). The build writes
 * its adapter into the module's generated catalog, which the module's MCP
 * endpoint serves; an agent lists it and reads it by its uri.
 */
#[ResourceInfo(
    uri: 'scaffold://scaffold-guide',
    name: 'scaffold-guide',
    description: 'Say what the resource holds and when an agent should read it.',
    needs: 'scaffold.scaffold-guide',
    mimeType: 'text/markdown',
)]
final readonly class ScaffoldGuideResource implements McpResource
{
    /**
     * @return string The resource's text.
     */
    #[Override]
    public function read(): string
    {
        return "# scaffold-guide\n\nWrite what an agent should read here.\n";
    }
}
