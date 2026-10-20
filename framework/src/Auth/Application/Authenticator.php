<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Session\Application\RequestSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

/**
 * Signs users in and out (#9): the signed-in user's id lives in the session
 * under `aic.user`. Login gives the session a fresh id; logout ends it.
 */
final readonly class Authenticator
{
    /**
     * The session key that keeps the signed-in user's id.
     */
    private const string USER = 'aic.user';

    /**
     * @param UserProvider   $userProvider   The app's users.
     * @param PasswordHasher $passwordHasher Checks passwords.
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private UserProvider $userProvider,
        private PasswordHasher $passwordHasher,
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The login post, with its session.
     * @param string                 $login         What the user signs in with.
     * @param string                 $password      Their password.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return bool Whether they are signed in now.
     */
    public function login(
        ServerRequestInterface $serverRequest,
        string $login,
        #[SensitiveParameter]
        string $password,
    ): bool {
        $credentials = $this->userProvider->credentials($login);

        if (!$credentials instanceof UserCredentials) {
            return false;
        }

        return $this->signIn($serverRequest, $credentials, $password);
    }

    /**
     * Signs the user out: the session ends with the request.
     *
     * @param ServerRequestInterface $serverRequest The logout post, with its session.
     *
     * @throws LogicException When the request carries no session.
     */
    public function logout(ServerRequestInterface $serverRequest): void
    {
        $this->requestSession->read($serverRequest)->clear();
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return string The signed-in user's id; empty for an anonymous visitor.
     */
    public function userId(ServerRequestInterface $serverRequest): string
    {
        return $this->requestSession->read($serverRequest)->get(self::USER) ?? '';
    }

    /**
     * Signs the user in when the password is theirs.
     *
     * @param ServerRequestInterface $serverRequest   The login post, with its session.
     * @param UserCredentials        $userCredentials The user's credentials.
     * @param string                 $password        The password sent.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return bool Whether the password matched and they are signed in now.
     */
    private function signIn(
        ServerRequestInterface $serverRequest,
        UserCredentials $userCredentials,
        #[SensitiveParameter]
        string $password,
    ): bool {
        if (!$this->passwordHasher->verify($password, $userCredentials->passwordHash)) {
            return false;
        }

        $session = $this->requestSession->read($serverRequest);
        $session->regenerateId();
        $session->set(self::USER, $userCredentials->userId);

        return true;
    }
}
