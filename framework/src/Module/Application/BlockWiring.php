<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Block\Contracts\Block;

/**
 * The modules' blocks, built with their services (#203); the generated wiring
 * implements it when some module declares a block whose services read a root.
 */
interface BlockWiring
{
    /**
     * @param HandlerContext $handlerContext The roots, from a context that holds no block yet.
     *
     * @return list<Block> The modules' blocks, in build order.
     */
    public function blocks(HandlerContext $handlerContext): array;
}
