<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Form\Contracts\FormToken;
use AIC\Http\Application\ResponseBuilder;
use AIC\Session\Application\RequestSession;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function hash_equals;
use function in_array;

/**
 * Guards every unsafe request before routing (#42): the CSRF token, sent as
 * the `aic_csrf` field or the `X-AIC-CSRF` header, must match the session's,
 * or the answer is 403; a filled honeypot field is a bot, answered 400. No
 * controller can skip it. It runs after SessionMiddleware.
 */
final readonly class FormGuardMiddleware implements MiddlewareInterface
{
    /**
     * The methods that change state.
     */
    private const array UNSAFE = ['DELETE', 'PATCH', 'POST', 'PUT'];

    /**
     * @param RequestSession  $requestSession  Reads the session off the request.
     * @param ResponseBuilder $responseBuilder Makes the refusals.
     * @param PostedFields    $postedFields    Reads the token and the honeypot.
     */
    public function __construct(
        private RequestSession $requestSession,
        private ResponseBuilder $responseBuilder,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, with its session.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return ResponseInterface The handler's response, or 403 or 400.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), self::UNSAFE, true)) {
            return $handler->handle($request);
        }

        $expected = $this->requestSession->read($request)->get(FormToken::SESSION_KEY) ?? '';
        $sent = $request->getHeaderLine(FormToken::HEADER);
        $sent = $sent === ''
            ? $this->postedFields->value($request, FormToken::FIELD)
            : $sent;

        if ($expected === '' || !hash_equals($expected, $sent)) {
            return $this->refusal(403, 'The form has expired or did not come from this site. Reload the page and send it again.');
        }

        if ($this->postedFields->value($request, FormToken::HONEYPOT) !== '') {
            return $this->refusal(400, 'The form could not be accepted.');
        }

        return $handler->handle($request);
    }

    /**
     * @param int    $status  403 or 400.
     * @param string $message What went wrong, for a person.
     *
     * @return ResponseInterface The refusal, as plain text.
     */
    private function refusal(int $status, string $message): ResponseInterface
    {
        return $this->responseBuilder->create($status, 'text/plain; charset=utf-8', $message . "\n");
    }
}
