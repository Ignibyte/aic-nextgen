<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Block\Contracts\Block;

/**
 * The modules' blocks when none of them reads a root (#203); the generated
 * wiring implements it instead of `BlockWiring`, so no context goes unread.
 */
interface RootlessBlockWiring
{
    /**
     * @return list<Block> The modules' blocks, in build order.
     */
    public function blocks(): array;
}
