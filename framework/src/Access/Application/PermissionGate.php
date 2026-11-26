<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\Actor;
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
 * `error="insufficient_scope"` for one that lacks the need. When the app is
 * an authorization server (#331), a visitor with no token asking for a
 * permission gets 401 with the protected resource's metadata
 * (`resource_metadata`, RFC 9728), so an OAuth client finds where to get a
 * token, and so does an unknown token; a signed-in person still gets 403.
 */
final readonly class PermissionGate implements RequestHandlerInterface
{
    /**
     * @param string                  $need             A declared permission, or `signed-in`.
     * @param RequestHandlerInterface $requestHandler   The controller's handler.
     * @param Actors                  $actors           The request's actor.
     * @param ResponseBuilder         $responseBuilder  Makes the 403.
     * @param string                  $resourceMetadata The protected resource's metadata URL; empty when the app is
     *                                                  no authorization server.
     */
    public function __construct(
        private string $need,
        private RequestHandlerInterface $requestHandler,
        private Actors $actors,
        private ResponseBuilder $responseBuilder,
        private string $resourceMetadata = '',
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
            return $this->anonymous($actor);
        }

        if ($actor->refused()) {
            $params = 'error="invalid_token"' . ($this->resourceMetadata === '' ? '' : ', ' . $this->metadata());

            return $this->challenged(401, 'The bearer token is not known.', $params);
        }

        $scope = 'error="insufficient_scope", scope="' . $this->need . '"';

        return $this->challenged(403, "This token's scopes do not hold " . $this->need . '.', $scope);
    }

    /**
     * @param Actor $actor A caller with no bearer token who lacks the need.
     *
     * @throws InvalidArgumentException When the refusal cannot be made.
     *
     * @return ResponseInterface 403; 401 with the resource's metadata for a visitor when the app is an
     *                           authorization server and the need is a permission a token can hold.
     */
    private function anonymous(Actor $actor): ResponseInterface
    {
        return $this->resourceMetadata === '' || $actor->signedIn() || $this->need === Actor::SIGNED_IN
            ? $this->refusal(403, 'This needs ' . $this->need . '.')
            : $this->challenged(401, 'This needs ' . $this->need . ', with a bearer token.', $this->metadata());
    }

    /**
     * @return string The challenge's `resource_metadata` parameter (RFC 9728 section 5.1).
     */
    private function metadata(): string
    {
        return 'resource_metadata="' . $this->resourceMetadata . '"';
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
