<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * One tool as `tools/list` shows it (#316), with the permission a caller needs,
 * as the build wrote it from `#[ToolInfo]` and the tool's `call()` signature,
 * and whether it runs dry (#332).
 */
final readonly class McpToolInfo implements JsonSerializable
{
    /**
     * @param string     $name        The tool's name.
     * @param string     $description What it does.
     * @param string     $needs       The permission a caller needs.
     * @param JsonSchema $input       Its arguments' schema.
     * @param JsonSchema $output      Its result's schema.
     * @param bool       $dryRun      Whether a caller may run it dry (#332).
     * @param bool       $progress    Whether it runs in steps, reporting progress between them (#333).
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $needs,
        public JsonSchema $input,
        public JsonSchema $output,
        public bool $dryRun = false,
        public bool $progress = false,
    ) {
    }

    /**
     * @return array<string, string|JsonSchema|array<string, bool>> The tool as MCP lists one, with `_meta`
     *                                                              holding `"aic/dryRun": true` when it runs dry
     *                                                              and `"aic/progress": true` when it runs in steps.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $tool = [
            'description' => $this->description,
            'inputSchema' => $this->input,
            'name' => $this->name,
            'outputSchema' => $this->output,
        ];

        $meta = [];

        if ($this->dryRun) {
            $meta['aic/dryRun'] = true;
        }

        if ($this->progress) {
            $meta['aic/progress'] = true;
        }

        return $meta === []
            ? $tool
            : ['_meta' => $meta, ...$tool];
    }
}
