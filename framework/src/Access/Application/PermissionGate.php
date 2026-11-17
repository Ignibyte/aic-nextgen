<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Http\Application\ResponseBuilder;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A route's need (#304), checked before its controller: the generated
 * wiring wraps a handler whose route says `needs:` in this gate. An actor
 * who holds the need reaches the handler; anyone else gets 403, plain text.
 * A visitor under a protected prefix was sent to sign in before routing.
 */
final readonly class PermissionGate implements RequestHandlerInterface
{
    /**
     * @param string                  $need            A declared permission, or `signed-in`.
     * @param RequestHandlerInterface $requestHandler  The controller's handler.
     * @param Actors                  $actors          The request's actor.
     * @param ResponseBuilder         $responseBuilder Makes the 403.
     */
    public function __construct(
        private string $need,
        private RequestHandlerInterface $requestHandler,
        private Actors $actors,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @throws QueryException When the actor's roles cannot be read.
     *
     * @return ResponseInterface The handler's answer, or 403.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->actors->actor($request)->can($this->need)
            ? $this->requestHandler->handle($request)
            : $this->responseBuilder->create(403, 'text/plain; charset=utf-8', 'This needs ' . $this->need . ".\n");
    }
}
