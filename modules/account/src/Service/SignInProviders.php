<?php

declare(strict_types=1);

namespace App\Account\Service;

use AIC\Auth\Application\CodeExchange;
use AIC\Auth\Application\OidcSignIn;
use AIC\Config\Domain\AppConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The account module's sign-in through OpenID Connect providers (#313), a
 * factory: the providers `config/app.php` names and the app's PSR-18 client
 * for the code exchange.
 */
final readonly class SignInProviders
{
    /**
     * The provider the fixture signs in through: gate:23's stub.
     */
    public const string PROVIDER = 'stub';

    /**
     * @param AppConfig               $appConfig      The app's settings, with its providers.
     * @param ClientInterface         $client         The app's PSR-18 client.
     * @param RequestFactoryInterface $requestFactory Makes the token endpoint's request.
     * @param StreamFactoryInterface  $streamFactory  Makes its body.
     */
    public function __construct(
        private AppConfig $appConfig,
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @return OidcSignIn The sign-in, for the start and the callback.
     */
    public function oidcSignIn(): OidcSignIn
    {
        return new OidcSignIn($this->appConfig->oidc, new CodeExchange($this->client, $this->requestFactory, $this->streamFactory));
    }
}
