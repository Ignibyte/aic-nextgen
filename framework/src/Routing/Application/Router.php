<?php

declare(strict_types=1);

namespace AIC\Routing\Application;

use AIC\Routing\Domain\MethodNotAllowedException;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Routing\Domain\RouteTable;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;

/**
 * The PSR-15 handler that routes: it matches the request against the table and
 * hands the matched route's handler the request, with the path parameters as
 * request attributes.
 */
final readonly class Router implements RequestHandlerInterface
{
    /**
     * @param RouteTable                             $routeTable The routes.
     * @param array<string, RequestHandlerInterface> $handlers   The handlers by id.
     *
     * @throws LogicException When a route names a handler that is not given.
     */
    public function __construct(
        private RouteTable $routeTable,
        private array $handlers,
    ) {
        foreach ($routeTable->handlers() as $handler) {
            if (!array_key_exists($handler, $handlers)) {
                throw new LogicException('A route names the handler "' . $handler . '", which is not given to the router.');
            }
        }
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @throws RouteNotFoundException    When no route fits the path.
     * @throws MethodNotAllowedException When the fitting routes answer other methods only.
     * @throws LogicException            When the matched route's handler is missing.
     *
     * @return ResponseInterface The matched handler's response.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $match = $this->routeTable->match($request->getMethod(), $request->getUri()->getPath());

        foreach ($match->parameters as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $handler = $this->handlers[$match->route->handler] ?? null;

        if (!$handler instanceof RequestHandlerInterface) {
            throw new LogicException('The router has no handler "' . $match->route->handler . '".');
        }

        return $handler->handle($request);
    }
}
