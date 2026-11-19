<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Auth\Domain\OidcProvider;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * A page's answer that sends the browser to a provider to sign in (#313):
 * the one redirect off the site, and only to a configured provider's
 * authorization endpoint, with the sign-in's query.
 */
final readonly class ProviderRedirect implements PageResponse
{
    /**
     * @param OidcProvider $oidcProvider The provider.
     * @param string       $query        The authorization request's query, already encoded.
     */
    public function __construct(
        private OidcProvider $oidcProvider,
        private string $query,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface 303 to the provider.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->redirect(303, $this->oidcProvider->authorizationEndpoint . '?' . $this->query);
    }
}
