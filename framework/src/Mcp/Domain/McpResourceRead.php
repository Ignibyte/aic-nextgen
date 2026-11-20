<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

/**
 * One resource's adapter (#316), written by the build and built with the
 * resource: its descriptor, from `#[ResourceInfo]`, and its read.
 */
interface McpResourceRead
{
    /**
     * @return McpResourceInfo The resource, as `resources/list` shows it.
     */
    public function resourceInfo(): McpResourceInfo;

    /**
     * @return string Its text now.
     */
    public function resourceText(): string;
}
