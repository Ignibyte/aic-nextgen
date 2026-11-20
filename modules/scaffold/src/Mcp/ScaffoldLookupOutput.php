<?php

declare(strict_types=1);

namespace App\Scaffold\Mcp;

/**
 * What the scaffold.scaffold-lookup tool answers, made by bin/aic make:mcp-tool (#316). The
 * build writes the tool's output schema and its JSON from this constructor.
 */
final readonly class ScaffoldLookupOutput
{
    /**
     * @param string $answer The answer to the query.
     */
    public function __construct(
        public string $answer,
    ) {
    }
}
