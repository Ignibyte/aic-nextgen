<?php

declare(strict_types=1);

namespace AIC\Block\Contracts;

use AIC\Component\Contracts\Component;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What a page shows beside its own content (#38): its placement names the
 * region, the weight and the pages it shows on, and for each page it makes
 * the component to show there, from that page's request (#58), so a
 * navigation block can mark the current page. Blocks are plugins, tagged
 * `Block` in the container, and every page gets the ones that match it.
 */
interface Block
{
    /**
     * @return Placement Where it shows.
     */
    public function placement(): Placement;

    /**
     * @param ServerRequestInterface $serverRequest The request of the page it is placed into.
     *
     * @return Component What it shows on that page.
     */
    public function component(ServerRequestInterface $serverRequest): Component;
}
