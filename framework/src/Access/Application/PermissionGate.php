<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Http\Application\ResponseBuilder;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A route's need (#304), checked before its controller: the generated
 * wiring wraps a handler whose route says `needs:` in this gate. An actor
 * who holds the need reaches the handler; anyone else gets 403, plain text.
 * A visitor under a protected prefix was sent to sign in before routing.
 * A bearer caller (#313) is answered as RFC 6750 says: 401 with
 * `error="invalid_token"` for a token no row matches, 403 with
 * `error="insufficient_scope"` for one that lacks the need.
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
     * @throws QueryException           When the actor's roles or token cannot be read.
     * @throws InvalidArgumentException When a refusal cannot be made.
     *
     * @return ResponseInterface The handler's answer, or 401 or 403.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->actors->actor($request);

        if ($actor->can($this->need)) {
            return $this->requestHandler->handle($request);
        }

        if (!$actor->bearer) {
            return $this->refusal(403, 'This needs ' . $this->need . '.');
        }

        if ($actor->refused()) {
            return $this->challenged(401, 'The bearer token is not known.', 'error="invalid_token"');
        }

        $scope = 'error="insufficient_scope", scope="' . $this->need . '"';

        return $this->challenged(403, "This token's scopes do not hold " . $this->need . '.', $scope);
    }

    /**
     * @param int    $status  401 or 403.
     * @param string $message Why, for whoever reads it.
     * @param string $params  The challenge's parameters (RFC 6750 section 3).
     *
     * @throws InvalidArgumentException When the status or the header is invalid.
     *
     * @return ResponseInterface The refusal, with its `WWW-Authenticate` challenge.
     */
    private function challenged(int $status, string $message, string $params): ResponseInterface
    {
        return $this->responseBuilder->withHeader($this->refusal($status, $message), 'WWW-Authenticate', 'Bearer ' . $params);
    }

    /**
     * @param int    $status  401 or 403.
     * @param string $message Why, for whoever reads it.
     *
     * @throws InvalidArgumentException When the status is out of range.
     *
     * @return ResponseInterface The refusal, as plain text.
     */
    private function refusal(int $status, string $message): ResponseInterface
    {
        return $this->responseBuilder->create($status, 'text/plain; charset=utf-8', $message . "\n");
    }
}
