<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

/**
 * The answer to `tools/call` (#316), as JSON text around a capability's
 * result (#332): the tool's output as structured content and, for a client
 * that reads only text, the same JSON as text; or, when the tool refused, its
 * reason as text with `isError`. A dry run adds `_meta: {"aic/dryRun": true}`.
 * Written as text so a replayed call answers the stored output's bytes.
 */
final readonly class McpToolResult
{
    /**
     * @param CapabilityResult $capabilityResult What the call answered.
     * @param McpJson          $mcpJson          Encodes the parts.
     */
    public function __construct(
        private CapabilityResult $capabilityResult,
        private McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @return string The result, encoded.
     */
    public function text(): string
    {
        $capabilityResult = $this->capabilityResult;
        $meta = $capabilityResult->dryRun
            ? ',"_meta":{"aic/dryRun":true}'
            : '';

        if ($capabilityResult->callOutcome !== CallOutcome::Done) {
            return '{"content":[{"type":"text","text":' . $this->mcpJson->text($capabilityResult->refusal) . '}],"isError":true'
                . $meta . '}';
        }

        return '{"content":[{"type":"text","text":' . $this->mcpJson->text($capabilityResult->output) . '}],"isError":false,'
            . '"structuredContent":' . $capabilityResult->output . $meta . '}';
    }
}
