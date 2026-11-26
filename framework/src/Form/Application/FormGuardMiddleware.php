<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Form\Contracts\FormToken;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\BearerCredential;
use AIC\Http\Domain\Webhook;
use AIC\Session\Application\RequestSession;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function hash_equals;
use function in_array;
use function strtolower;
use function trim;

/**
 * Guards every unsafe request before routing (#42): the CSRF token, sent as
 * the `aic_csrf` field or the `X-AIC-CSRF` header, must match the session's,
 * or the answer is 403; a filled honeypot field is a bot, answered 400. No
 * controller can skip it. It runs after SessionMiddleware. A request with
 * a bearer token (#313) passes: a browser cannot send that header to another
 * site without a CORS preflight, and such a request is judged by its token
 * alone, never by the session's user. So does a webhook delivery whose
 * signature `WebhookSignatureMiddleware` verified (#321), and a JSON body
 * (#331): no form, link or no-cors fetch can send `application/json` to
 * another site, only a script the site's own CORS answer allows, and AIC
 * answers none; so an MCP client with no token yet reaches its route's need
 * and its 401 challenge.
 */
final readonly class FormGuardMiddleware implements MiddlewareInterface
{
    /**
     * The methods that change state.
     */
    private const array UNSAFE = ['DELETE', 'PATCH', 'POST', 'PUT'];

    /**
     * @param RequestSession   $requestSession   Reads the session off the request.
     * @param ResponseBuilder  $responseBuilder  Makes the refusals.
     * @param PostedFields     $postedFields     Reads the token and the honeypot.
     * @param BearerCredential $bearerCredential Tells a bearer request.
     */
    public function __construct(
        private RequestSession $requestSession,
        private ResponseBuilder $responseBuilder,
        private PostedFields $postedFields = new PostedFields(),
        private BearerCredential $bearerCredential = new BearerCredential(),
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, with its session.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return ResponseInterface The handler's response, or 403 (419 to Tessera) or 400.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->guards($request)) {
            return $handler->handle($request);
        }

        $expected = $this->requestSession->read($request)->get(FormToken::SESSION_KEY) ?? '';
        $sent = $request->getHeaderLine(FormToken::HEADER);
        $sent = $sent === ''
            ? $this->postedFields->value($request, FormToken::FIELD)
            : $sent;

        if ($expected === '' || !hash_equals($expected, $sent)) {
            return $this->refusal(
                self::expired($request),
                'The form has expired or did not come from this site. Reload the page and send it again.',
            );
        }

        if ($this->postedFields->value($request, FormToken::HONEYPOT) !== '') {
            return $this->refusal(400, 'The form could not be accepted.');
        }

        return $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return bool Whether it is unsafe and judged by its session, not by a bearer token or a webhook signature (#321).
     */
    private function guards(ServerRequestInterface $serverRequest): bool
    {
        return in_array($serverRequest->getMethod(), self::UNSAFE, true)
            && !$this->bearerCredential->present($serverRequest)
            && $serverRequest->getAttribute(Webhook::ATTRIBUTE) === null
            && strtolower(trim(explode(';', $serverRequest->getHeaderLine('Content-Type'))[0])) !== 'application/json';
    }

    /**
     * @param int    $status  403, 419 or 400.
     * @param string $message What went wrong, for a person.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The refusal, as plain text.
     */
    private function refusal(int $status, string $message): ResponseInterface
    {
        return $this->responseBuilder->create($status, 'text/plain; charset=utf-8', $message . "\n");
    }

    /**
     * @param ServerRequestInterface $serverRequest A request whose token is stale or missing.
     *
     * @return int 419 for Tessera, whose "page expired" prompt it raises (protocol 6, #71); 403 for a plain browser.
     */
    private static function expired(ServerRequestInterface $serverRequest): int
    {
        return $serverRequest->getHeaderLine(TesseraRequest::REQUEST) === ''
            ? 403
            : 419;
    }
}
