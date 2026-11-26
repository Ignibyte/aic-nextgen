<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Config\Domain\AppConfig;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wraps a handler in its route's need (#304), for the generated wiring's
 * adapters: no need leaves the handler as it is.
 */
final readonly class AccessGates
{
    /**
     * @param Database        $database        The connection the roles are read through.
     * @param ResponseBuilder $responseBuilder Makes the 403.
     * @param AppConfig       $appConfig       The app's settings: an authorization server's metadata is named in a
     *                                         refusal (#331).
     */
    public function __construct(
        private Database $database,
        private ResponseBuilder $responseBuilder,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param string                  $need           A declared permission, `signed-in`, or empty for none.
     * @param RequestHandlerInterface $requestHandler A controller's handler.
     *
     * @return RequestHandlerInterface The handler, behind a `PermissionGate` when it has a need.
     */
    public function gate(string $need, RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        if ($need === '') {
            return $requestHandler;
        }

        $actors = new Actors(new PostgresRoles($this->database), new BearerTokens($this->database));

        return new PermissionGate(
            $need,
            $requestHandler,
            $actors,
            $this->responseBuilder,
            $this->appConfig->oauth->resourceMetadata(),
        );
    }
}
