<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The optional middleware a module contributes around the framework stack.
 * Keeps module dispatch separate from the kernel and context construction.
 */
final readonly class ModuleLayers
{
    /**
     * An if per interface, not `match (true)`: elephc narrows `instanceof` in an
     * if, not in a match arm (#112).
     *
     * @param WebWiring      $webWiring      The modules' wiring.
     * @param HandlerContext $handlerContext The roots.
     *
     * @return list<MiddlewareInterface> The modules' middleware after the session; none when no module has any.
     */
    public function afterSession(WebWiring $webWiring, HandlerContext $handlerContext): array
    {
        if ($webWiring instanceof MiddlewareWiring) {
            return $webWiring->afterSession($handlerContext);
        }

        if ($webWiring instanceof RootlessMiddlewareWiring) {
            return $webWiring->afterSession();
        }

        return [];
    }
}
