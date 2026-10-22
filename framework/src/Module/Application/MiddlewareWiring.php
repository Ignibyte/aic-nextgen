<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The modules' middleware (#93), for the app kernel's stack; the generated
 * wiring implements it when some module declares middleware.
 */
interface MiddlewareWiring
{
    /**
     * @param HandlerContext $handlerContext The roots.
     *
     * @return list<MiddlewareInterface> The modules' middleware that runs after the session, in build order.
     */
    public function afterSession(HandlerContext $handlerContext): array;
}
