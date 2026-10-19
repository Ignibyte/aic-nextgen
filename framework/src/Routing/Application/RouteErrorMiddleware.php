<?php

declare(strict_types=1);

namespace AIC\Routing\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\Routing\Domain\MethodNotAllowedException;
use AIC\Routing\Domain\RouteNotFoundException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function implode;

/**
 * Answers the router's misses: 404 when no route fits the path, 405 with an
 * `Allow` header when the fitting routes answer other methods.
 */
final readonly class RouteErrorMiddleware implements MiddlewareInterface
{
    /**
     * The error responses' content type.
     */
    private const string PLAIN_TEXT = 'text/plain; charset=utf-8';

    /**
     * @param ResponseBuilder $responseBuilder Makes the error responses.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @return ResponseInterface The handler's response, or the 404 or 405.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RouteNotFoundException) {
            return $this->responseBuilder->create(404, self::PLAIN_TEXT, 'Not Found');
        } catch (MethodNotAllowedException $methodNotAllowedException) {
            return $this->responseBuilder->withHeader(
                $this->responseBuilder->create(405, self::PLAIN_TEXT, 'Method Not Allowed'),
                'Allow',
                implode(', ', $methodNotAllowedException->allowed),
            );
        }
    }
}
