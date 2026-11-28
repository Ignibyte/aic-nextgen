<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\Actor;
use AIC\Auth\Application\Authenticator;
use AIC\Database\Contracts\QueryException;
use AIC\Http\Domain\BearerCredential;
use AIC\Session\Application\RequestSession;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The request's actor (#304): the user id the session holds since sign-in,
 * and, for a signed-in user, the permissions their roles grant, read once
 * per call. A visitor is an actor with no id and no permission. A request
 * with a bearer token (#313) is its token's caller, and its session's user
 * is never read.
 */
final readonly class Actors
{
    /**
     * @param PostgresRoles    $postgresRoles    The roles.
     * @param BearerTokens     $bearerTokens     The tokens.
     * @param RequestSession   $requestSession   The request's session.
     * @param BearerCredential $bearerCredential Reads a bearer token.
     */
    public function __construct(
        private PostgresRoles $postgresRoles,
        private BearerTokens $bearerTokens,
        private RequestSession $requestSession = new RequestSession(),
        private BearerCredential $bearerCredential = new BearerCredential(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request that passed the session middleware.
     *
     * @throws QueryException When the roles or the tokens cannot be read.
     *
     * @return Actor Who asks.
     */
    public function actor(ServerRequestInterface $serverRequest): Actor
    {
        if ($this->bearerCredential->present($serverRequest)) {
            return $this->bearerTokens->actor(
                $this->bearerCredential->token($serverRequest),
                $serverRequest->getUri()->getPath(),
            );
        }

        $userId = $this->requestSession->read($serverRequest)->get(Authenticator::USER) ?? '';

        return $userId === ''
            ? new Actor('', [])
            : new Actor($userId, $this->postgresRoles->permissions($userId));
    }
}
