<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\Actor;
use AIC\Auth\Application\Authenticator;
use AIC\Database\Contracts\QueryException;
use AIC\Session\Application\RequestSession;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The request's actor (#304): the user id the session holds since sign-in,
 * and, for a signed-in user, the permissions their roles grant, read once
 * per call. A visitor is an actor with no id and no permission.
 */
final readonly class Actors
{
    /**
     * @param PostgresRoles  $postgresRoles  The roles.
     * @param RequestSession $requestSession The request's session.
     */
    public function __construct(
        private PostgresRoles $postgresRoles,
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request that passed the session middleware.
     *
     * @throws QueryException When the roles cannot be read.
     *
     * @return Actor Who asks.
     */
    public function actor(ServerRequestInterface $serverRequest): Actor
    {
        $userId = $this->requestSession->read($serverRequest)->get(Authenticator::USER) ?? '';

        return $userId === ''
            ? new Actor('', [])
            : new Actor($userId, $this->postgresRoles->permissions($userId));
    }
}
