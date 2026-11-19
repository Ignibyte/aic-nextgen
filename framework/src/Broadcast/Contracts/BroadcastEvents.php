<?php

declare(strict_types=1);

namespace AIC\Broadcast\Contracts;

use AIC\Controller\Contracts\ServerEvent;

/**
 * How a stream sends a channel's events (#314): a module turns each published
 * event into the Server-Sent Event its page reads, such as a Tessera element
 * patch, with the event's id as its id.
 */
interface BroadcastEvents
{
    /**
     * @param Broadcast $broadcast A published event.
     *
     * @return ServerEvent It on the stream; its id is the broadcast's, so a reconnect resumes after it.
     */
    public function event(Broadcast $broadcast): ServerEvent;
}
