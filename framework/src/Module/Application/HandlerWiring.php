<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * The modules' handlers (#93), for the app kernel's router; the generated
 * wiring implements it when some module has a route.
 */
interface HandlerWiring
{
    /**
     * @param HandlerContext $handlerContext The roots and the kind wrappers.
     *
     * @return array<string, RequestHandlerInterface> The modules' handlers by id.
     */
    public function handlers(HandlerContext $handlerContext): array;
}
