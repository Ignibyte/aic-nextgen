<?php

declare(strict_types=1);

namespace AIC\Auth\Contracts;

use RuntimeException;

/**
 * A sign-in through a provider that did not happen (#313): the state did
 * not match, the provider refused or answered what the client cannot accept,
 * or an id token claim failed its check. The message says which, for the
 * person who tried.
 */
final class SignInRefusedException extends RuntimeException
{
}
