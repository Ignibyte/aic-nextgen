<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use LogicException;

/**
 * Puts a stream's event on the wire (#115). An element patch carries a component, not markup; the writer renders it
 * through the one Renderer when the event body pulls it.
 */
interface EventWriter
{
    /**
     * @param ServerEvent $serverEvent The event the source sent.
     *
     * @throws LogicException When no template renders the event's component.
     *
     * @return string The event on the wire, its component rendered as `elements` lines.
     */
    public function frame(ServerEvent $serverEvent): string;
}
