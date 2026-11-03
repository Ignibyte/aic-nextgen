<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Auth\Domain\SignedInUser;
use AIC\Session\Application\RequestSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

/**
 * Signs users in and out (#9): the signed-in user's id lives in the session
 * under `aic.user`. Login gives the session a fresh id; logout ends it. A
 * login with no user still pays one bcrypt comparison, against a decoy hash,
 * so its timing does not tell which logins exist (#177).
 */
final readonly class Authenticator
{
    /**
     * The session key that keeps the signed-in user's id.
     */
    public const string USER = 'aic.user';

    /**
     * A bcrypt hash at the hasher's cost (12) of a random password no one holds: an unknown login is checked
     * against it, so it costs what a wrong password costs.
     */
    private const string DECOY = '$2a$12$kwbcFx/.3yFX/abNQvcKEONLEoU7b7MRsLyTXuIOIRBZAG9Ukq3Gm';

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
     * @return SignedInUser|null Who is signed in now, with what the provider read for the app (#238); null when the
     *                           login or the password is wrong.
     */
    public function login(
        ServerRequestInterface $serverRequest,
        string $login,
        #[SensitiveParameter]
        string $password,
    ): ?SignedInUser {
        $credentials = $this->userProvider->credentials($login);

        if (!$credentials instanceof UserCredentials) {
            $this->passwordHasher->verify($password, self::DECOY);

            return null;
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
     * @return SignedInUser|null Who is signed in now; null when the password did not match.
     */
    private function signIn(
        ServerRequestInterface $serverRequest,
        UserCredentials $userCredentials,
        #[SensitiveParameter]
        string $password,
    ): ?SignedInUser {
        if (!$this->passwordHasher->verify($password, $userCredentials->passwordHash)) {
            return null;
        }

        $signedInUser = new SignedInUser($userCredentials->userId, $userCredentials->profile);
        $session = $this->requestSession->read($serverRequest);
        $session->regenerateId();
        $session->set(self::USER, $signedInUser->userId);

        return $signedInUser;
    }
}
