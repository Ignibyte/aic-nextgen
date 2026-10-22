<?php

declare(strict_types=1);

namespace AIC\Session\Application;

use AIC\Session\Contracts\Session;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

/**
 * Reads the visitor's session off the request, where SessionMiddleware put
 * it as an attribute. The attribute lives and dies with the request.
 */
final readonly class RequestSession
{
    /**
     * The request attribute that carries the session.
     */
    public const string ATTRIBUTE = 'aic.session';

    /**
     * @param ServerRequestInterface $serverRequest A request that passed SessionMiddleware.
     *
     * @throws LogicException When it carries no session: the middleware is not in the stack.
     *
     * @return Session The session.
     */
    public function read(ServerRequestInterface $serverRequest): Session
    {
        $attributes = $serverRequest->getAttributes();

        if (array_key_exists(self::ATTRIBUTE, $attributes) && $attributes[self::ATTRIBUTE] instanceof Session) {
            return $attributes[self::ATTRIBUTE];
        }

        throw new LogicException('The request carries no session: add SessionMiddleware to the stack.');
    }
}
