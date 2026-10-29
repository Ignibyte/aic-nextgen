<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Component\Application\ComponentTemplates;
use AIC\Container\Exception\ContainerException;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Server\MiddlewareInterface;

/**
 * The optional middleware and blocks modules contribute (#203). Keeps module
 * dispatch separate from the kernel and context construction.
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

    /**
     * Builds the handler context in two steps (#203): one with no block gives the blocks' services their roots,
     * then the context with the blocks they make.
     *
     * @param WebWiring          $webWiring          The modules' wiring.
     * @param WebRoots           $webRoots           The typed constructor roots.
     * @param ResponseBuilder    $responseBuilder    Makes the responses.
     * @param ComponentTemplates $componentTemplates The compiled templates.
     *
     * @throws ContainerException When a block's service cannot be built.
     *
     * @return HandlerContext The context, with the modules' blocks.
     */
    public function context(
        WebWiring $webWiring,
        WebRoots $webRoots,
        ResponseBuilder $responseBuilder,
        ComponentTemplates $componentTemplates,
    ): HandlerContext {
        $handlerContext = new HandlerContext(new HttpHandlers($responseBuilder, $componentTemplates), $webRoots);

        return new HandlerContext(
            new HttpHandlers($responseBuilder, $componentTemplates, $this->blocks($webWiring, $handlerContext)),
            $webRoots,
        );
    }

    /**
     * @param WebWiring      $webWiring      The compiled request roots.
     * @param HandlerContext $handlerContext A context holding no block yet, whose roots the blocks' services read.
     *
     * @throws ContainerException When a block's service cannot be built.
     *
     * @return Blocks The modules' blocks, built with their services (#203).
     */
    private function blocks(WebWiring $webWiring, HandlerContext $handlerContext): Blocks
    {
        if ($webWiring instanceof BlockWiring) {
            return new Blocks($webWiring->blocks($handlerContext));
        }

        return $webWiring instanceof RootlessBlockWiring
            ? new Blocks($webWiring->blocks())
            : new Blocks([]);
    }
}
