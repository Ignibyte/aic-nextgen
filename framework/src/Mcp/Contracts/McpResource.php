<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

/**
 * A resource an app offers agents over MCP (#316): a readonly class of this
 * type in a module's `src/Mcp/`, marked `#[ResourceInfo]` with its URI, which
 * `resources/read` answers with the text `read()` returns.
 */
interface McpResource
{
    /**
     * @return string The resource's text now, of the media type its `#[ResourceInfo]` names.
     */
    public function read(): string;
}
