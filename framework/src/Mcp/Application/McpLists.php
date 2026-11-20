<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Domain\McpListResult;
use AIC\Mcp\Domain\McpPromptInfo;
use AIC\Mcp\Domain\McpResourceInfo;
use AIC\Mcp\Domain\McpToolInfo;

/**
 * The three lists of an MCP server (#316): each entry the caller has the
 * permission for, as its descriptor.
 */
final readonly class McpLists
{
    /**
     * @param string                                                      $key   `tools`, `resources` or `prompts`.
     * @param list<McpToolInfo>|list<McpResourceInfo>|list<McpPromptInfo> $infos The entries' descriptors.
     * @param Actor                                                       $actor Who asks.
     *
     * @return McpListResult The descriptors of the ones the caller may use.
     */
    public function visible(string $key, array $infos, Actor $actor): McpListResult
    {
        $visible = [];

        foreach ($infos as $info) {
            if (!$actor->can($info->needs)) {
                continue;
            }

            $visible[] = $info;
        }

        return new McpListResult($key, $visible);
    }
}
