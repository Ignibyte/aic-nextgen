<?php

declare(strict_types=1);

namespace AIC\Mcp\Attribute;

use Attribute;

/**
 * Names and describes an `McpTool` (#316). The build reads it with the tool's
 * `call()` signature; it does nothing at run time.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ToolInfo
{
    /**
     * @param string $name        The tool's name, lower-case words joined by "." (`ledger.split`), unique in the app.
     * @param string $description What it does and when an agent should call it.
     * @param string $needs       The permission a caller needs to see and call it, declared by some module.
     * @param bool   $dryRun      Whether a caller may run it dry (#332): the call runs in a savepoint rolled back,
     *                            so declare it only when every effect is in the database (no outbound request,
     *                            no stored file).
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $needs,
        public bool $dryRun = false,
    ) {
    }
}
