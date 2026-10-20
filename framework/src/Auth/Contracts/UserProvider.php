<?php

declare(strict_types=1);

namespace AIC\Auth\Contracts;

/**
 * The app's users, as the Authenticator reads them (#9): the app owns its
 * users table and implements this.
 */
interface UserProvider
{
    /**
     * @param string $login What the user signs in with.
     *
     * @return UserCredentials|null Their credentials; null when there is no such user.
     */
    public function credentials(string $login): ?UserCredentials;
}
