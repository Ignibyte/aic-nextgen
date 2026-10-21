<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\Tessera\Domain\TesseraRequest;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;

/**
 * Hands a redirect to Tessera as a header (#71, protocol 5.4): `fetch` hides
 * a write's `Location` and would resend its body on a `307`, so every
 * redirect answering a Tessera request becomes `200` with `Tessera-Location`,
 * its cookies and any `Tessera-Full-Visit` kept, and an empty body. A plain
 * request keeps the real redirect. It sits at the edge, so every route and
 * middleware redirect passes through it.
 */
final readonly class TesseraRedirectMiddleware implements MiddlewareInterface
{
    private const array REDIRECTS = [301, 302, 303, 307, 308];

    /**
     * @param ResponseBuilder $responseBuilder Makes the answer Tessera reads.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @return ResponseInterface The rest of the stack's answer, a redirect turned into `Tessera-Location` for Tessera.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (
            $request->getHeaderLine(TesseraRequest::REQUEST) === ''
            || !in_array($response->getStatusCode(), self::REDIRECTS, true)
        ) {
            return $response;
        }

        $answer = $this->responseBuilder->create(200, 'text/plain; charset=utf-8', '');
        $answer = $this->responseBuilder->withHeader($answer, 'Tessera-Location', $response->getHeaderLine('Location'));
        $answer = $this->responseBuilder->withHeader($answer, TesseraRequest::PROTOCOL, TesseraRequest::VERSION);
        $answer = $this->responseBuilder->withHeader($answer, 'Vary', TesseraRequest::VARY);

        return $this->withKept($answer, $response);
    }

    /**
     * @param ResponseInterface $answer   The `Tessera-Location` answer.
     * @param ResponseInterface $redirect The redirect it replaces.
     *
     * @return ResponseInterface The answer with the redirect's cookies and its `Tessera-Full-Visit`, if any.
     */
    private function withKept(ResponseInterface $answer, ResponseInterface $redirect): ResponseInterface
    {
        foreach ($redirect->getHeader('Set-Cookie') as $cookie) {
            $answer = $this->responseBuilder->withAddedHeader($answer, 'Set-Cookie', $cookie);
        }

        $fullVisit = $redirect->getHeaderLine(TesseraRequest::FULL_VISIT);

        return $fullVisit === ''
            ? $answer
            : $this->responseBuilder->withHeader($answer, TesseraRequest::FULL_VISIT, $fullVisit);
    }
}
