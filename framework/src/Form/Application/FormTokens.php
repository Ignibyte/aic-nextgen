<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Component\Domain\GuardFields;
use AIC\Form\Contracts\FormToken;
use AIC\Session\Application\RequestSession;
use AIC\Session\Domain\RandomTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Issues the visitor's CSRF token: one per session, made on first use and
 * kept under FormToken::SESSION_KEY, so several tabs share it.
 */
final readonly class FormTokens
{
    /**
     * @param RequestSession $requestSession Reads the session off the request.
     * @param RandomTokens   $randomTokens   Makes the token.
     */
    public function __construct(
        private RequestSession $requestSession = new RequestSession(),
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return FormToken The session's token.
     */
    public function issue(ServerRequestInterface $serverRequest): FormToken
    {
        $session = $this->requestSession->read($serverRequest);
        $token = $session->get(FormToken::SESSION_KEY) ?? '';

        if ($token === '') {
            $token = $this->randomTokens->fresh();
            $session->set(FormToken::SESSION_KEY, $token);
        }

        return new FormToken($token);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return GuardFields The session's token and the honeypot, for a form to render first (#246).
     */
    public function guard(ServerRequestInterface $serverRequest): GuardFields
    {
        return new GuardFields($this->issue($serverRequest)->value());
    }
}
