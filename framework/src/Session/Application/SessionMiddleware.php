<?php

declare(strict_types=1);

namespace AIC\Session\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\Session\Contracts\SessionStore;
use AIC\Session\Domain\RandomTokens;
use AIC\Session\Domain\StoredSession;
use AIC\Session\Domain\StringMapJson;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;

/**
 * Loads the visitor's session from its `__Host-aic_session` cookie, puts it
 * on the request (RequestSession), and after the handler saves it and sets
 * the cookie; a session left empty is destroyed and its cookie cleared. An
 * unknown or expired id gets a fresh session with a fresh id, never the one
 * the visitor sent. A request with no cookie that leaves the session empty
 * never touches the store.
 */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    /**
     * The cookie: host-only, so Secure, Path=/ and no Domain.
     */
    private const string COOKIE = '__Host-aic_session';

    /**
     * @param SessionStore    $sessionStore    Where sessions live.
     * @param RandomTokens    $randomTokens    Makes fresh ids.
     * @param StringMapJson   $stringMapJson   Encodes and decodes payloads.
     * @param ResponseBuilder $responseBuilder Adds the cookie to the response.
     * @param int             $ttl             Seconds a session lives after its last request.
     */
    public function __construct(
        private SessionStore $sessionStore,
        private RandomTokens $randomTokens,
        private StringMapJson $stringMapJson,
        private ResponseBuilder $responseBuilder,
        private int $ttl,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws JsonException When the session cannot be encoded.
     *
     * @return ResponseInterface The response, with the session cookie set or cleared when it changed.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $incoming = $this->cookie($request);
        $storedSession = $this->load($incoming);
        $response = $handler->handle($request->withAttribute(RequestSession::ATTRIBUTE, $storedSession));

        if ($storedSession->all() === []) {
            return $this->discard($incoming, $response);
        }

        if ($incoming !== '' && $incoming !== $storedSession->sessionId()) {
            $this->sessionStore->destroy($incoming);
        }

        $this->sessionStore->write($storedSession->sessionId(), $this->stringMapJson->encode($storedSession->all()), $this->ttl);

        return $this->responseBuilder->withAddedHeader($response, 'Set-Cookie', $this->setCookie($storedSession->sessionId(), $this->ttl));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string The session cookie's value; empty for none.
     */
    private function cookie(ServerRequestInterface $serverRequest): string
    {
        $cookies = $serverRequest->getCookieParams();
        $value = $cookies[self::COOKIE] ?? '';

        return is_string($value)
            ? $value
            : '';
    }

    /**
     * @param string $incoming The id the visitor sent; empty for none.
     *
     * @return StoredSession Its session, or a fresh one under a fresh id.
     */
    private function load(string $incoming): StoredSession
    {
        $payload = $incoming === ''
            ? null
            : $this->sessionStore->read($incoming);

        return $payload === null
            ? new StoredSession($this->randomTokens->fresh(), [], $this->randomTokens)
            : new StoredSession($incoming, $this->stringMapJson->decode($payload), $this->randomTokens);
    }

    /**
     * @param string            $incoming The id the visitor sent; empty for none.
     * @param ResponseInterface $response The handler's response.
     *
     * @return ResponseInterface It, clearing the cookie the visitor sent.
     */
    private function discard(string $incoming, ResponseInterface $response): ResponseInterface
    {
        if ($incoming === '') {
            return $response;
        }

        $this->sessionStore->destroy($incoming);

        return $this->responseBuilder->withAddedHeader($response, 'Set-Cookie', $this->setCookie('', 0));
    }

    /**
     * @param string $id     The cookie's value.
     * @param int    $maxAge Seconds it lives; 0 clears it.
     *
     * @return string The `Set-Cookie` value.
     */
    private function setCookie(string $id, int $maxAge): string
    {
        return self::COOKIE . '=' . $id . '; Path=/; Max-Age=' . $maxAge . '; Secure; HttpOnly; SameSite=Lax';
    }
}
