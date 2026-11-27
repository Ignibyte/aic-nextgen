<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Domain\McpCatalog;
use AIC\Mcp\Domain\McpJson;
use AIC\Mcp\Domain\McpListResult;
use AIC\Mcp\Domain\McpPromptInfo;
use AIC\Mcp\Domain\McpResourceInfo;
use AIC\Mcp\Domain\McpToolInfo;

use function hash;
use function substr;

/**
 * The three lists of an MCP server (#316): each entry the caller has the
 * permission for, as its descriptor, and a print of each list, which tells the
 * GET stream when one changed (#333).
 */
final readonly class McpLists
{
    /**
     * The lists, in the order of their prints.
     */
    public const array KEYS = ['tools', 'resources', 'prompts'];

    /**
     * @param McpJson $mcpJson Encodes a list for its print.
     */
    public function __construct(
        private McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @param McpCatalog $mcpCatalog The tools, resources and prompts.
     * @param Actor      $actor      Who asks.
     *
     * @return list<string> Each list's print, in `KEYS` order: the first 8 hex digits of the SHA-256 of what the
     *                      caller would be sent.
     */
    public function prints(McpCatalog $mcpCatalog, Actor $actor): array
    {
        $lists = [
            $this->visible('tools', $mcpCatalog->toolInfos(), $actor),
            $this->visible('resources', $mcpCatalog->resourceInfos(), $actor),
            $this->visible('prompts', $mcpCatalog->promptInfos(), $actor),
        ];
        $prints = [];

        foreach ($lists as $list) {
            $prints[] = substr(hash('sha256', $this->mcpJson->encode($list)), 0, 8);
        }

        return $prints;
    }

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
