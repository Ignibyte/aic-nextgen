<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The modules' middleware after the session when none of it reads a root
 * (#93), for the app kernel; the generated wiring implements it instead of
 * `MiddlewareWiring`, so no context goes unread.
 */
interface RootlessMiddlewareWiring
{
    /**
     * @return list<MiddlewareInterface> The modules' middleware that runs after the session, in build order.
     */
    public function afterSession(): array;
}
