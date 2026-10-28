<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Http\Application\ResponseBuilder;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function ctype_digit;
use function strlen;

/**
 * Refuses a request whose body is larger than the app's cap (#196),
 * `AppConfig::$maxBodyBytes`, with 413 before the session is read, so PHP and
 * the compiled server answer an oversized upload alike. The size is the
 * `Content-Length` the client sent, else the body's own.
 */
final readonly class BodyLimitMiddleware implements MiddlewareInterface
{
    /**
     * The most digits a length can have and still fit an int.
     */
    private const int DIGITS = 18;

    /**
     * @param ResponseBuilder $responseBuilder Makes the refusal.
     * @param int             $maxBodyBytes    The largest body taken.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private int $maxBodyBytes,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws InvalidArgumentException When the refusal cannot be built.
     *
     * @return ResponseInterface The handler's response, or 413 for a body over the cap.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->size($request) > $this->maxBodyBytes
            ? $this->responseBuilder->create(413, 'text/plain; charset=utf-8', 'Payload Too Large')
            : $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return int Its body's size: the declared length, else the body's; past the cap when the length is too
     *             long to read.
     */
    private function size(ServerRequestInterface $serverRequest): int
    {
        $length = $serverRequest->getHeaderLine('Content-Length');

        if ($length === '' || !ctype_digit($length)) {
            return $serverRequest->getBody()->getSize() ?? 0;
        }

        return strlen($length) > self::DIGITS
            ? $this->maxBodyBytes + 1
            : (int) $length;
    }
}
