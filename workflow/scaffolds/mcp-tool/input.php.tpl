<?php

declare(strict_types=1);

namespace {namespace}\Mcp;

/**
 * What the {name} tool takes, made by bin/aic make:mcp-tool (#316). The build
 * writes the tool's input schema from this constructor: each parameter's type,
 * default and description.
 */
final readonly class {stem}Input
{
    /**
     * @param string $query What the agent asks.
     */
    public function __construct(
        public string $query,
    ) {
    }
}
