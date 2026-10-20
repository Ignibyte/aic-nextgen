<?php

declare(strict_types=1);

namespace AIC\Block\Contracts;

use AIC\Component\Contracts\Component;

/**
 * A component that places itself (#38): its placement names the region, the
 * weight and the pages it shows on. Blocks are plugins, tagged `Block` in
 * the container, and every page gets the ones that match it.
 */
interface Block extends Component
{
    /**
     * @return Placement Where it shows.
     */
    public function placement(): Placement;
}
