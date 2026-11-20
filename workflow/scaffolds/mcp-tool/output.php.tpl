<?php

declare(strict_types=1);

namespace {namespace}\Mcp;

/**
 * What the {name} tool answers, made by bin/aic make:mcp-tool (#316). The
 * build writes the tool's output schema and its JSON from this constructor.
 */
final readonly class {stem}Output
{
    /**
     * @param string $answer The answer to the query.
     */
    public function __construct(
        public string $answer,
    ) {
    }
}
