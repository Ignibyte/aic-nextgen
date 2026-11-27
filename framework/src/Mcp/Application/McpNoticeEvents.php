<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Broadcast\Contracts\Broadcast;
use AIC\Broadcast\Contracts\BroadcastEvents;
use AIC\Controller\Contracts\ServerEvent;
use Override;

/**
 * The `aic.mcp` channel's events on MCP's GET stream (#333): each broadcast's
 * data is one JSON-RPC notification, sent as a `message` event under the
 * broadcast's id. Nothing publishes on the channel yet; the stream holds on it
 * for the broadcast hold, cap and keep-alive.
 */
final readonly class McpNoticeEvents implements BroadcastEvents
{
    /**
     * @param Broadcast $broadcast A published notification.
     *
     * @return ServerEvent It as a `message` event.
     */
    #[Override]
    public function event(Broadcast $broadcast): ServerEvent
    {
        return new ServerEvent('message', $broadcast->data, false, (string) $broadcast->id);
    }
}
