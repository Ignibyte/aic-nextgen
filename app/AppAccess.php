<?php

declare(strict_types=1);

namespace App;

use AIC\Auth\Application\Authenticator;
use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Auth\Application\RequireAuthenticationMiddleware;
use AIC\Auth\Domain\RouteAuthConfig;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use App\Auth\UserRepository;

/**
 * Who may reach what (#9): /account and the paths below it need a signed-in
 * user, and an anonymous visitor goes to /login.
 */
final readonly class AppAccess
{
    /**
     * @param Database        $database        Holds the users.
     * @param ResponseBuilder $responseBuilder Makes the redirect.
     */
    public function __construct(
        private Database $database,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @return RequireAuthenticationMiddleware The sign-in check.
     */
    public function middleware(): RequireAuthenticationMiddleware
    {
        $authenticator = new Authenticator(new UserRepository($this->database), new PgcryptoPasswordHasher($this->database));

        return new RequireAuthenticationMiddleware($authenticator, new RouteAuthConfig(['/account'], '/login'), $this->responseBuilder);
    }
}
