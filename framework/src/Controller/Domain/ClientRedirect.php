<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\OAuth\Domain\AuthorizationRequest;
use Override;
use Psr\Http\Message\ResponseInterface;

use function rawurlencode;
use function str_contains;

/**
 * A page's answer to an authorization request (#331): the browser goes back
 * to the client, only ever to the redirect URI the request named and the
 * client registered, with the code or the error and the client's state.
 */
final readonly class ClientRedirect implements PageResponse
{
    /**
     * @param AuthorizationRequest  $authorizationRequest The request answered, checked against the client.
     * @param array<string, string> $parameters           `code`, or `error` and `error_description`.
     */
    public function __construct(
        private AuthorizationRequest $authorizationRequest,
        private array $parameters,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface 303 to the client's redirect URI.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        $query = '';

        foreach ($this->parameters as $name => $value) {
            $query .= ($query === '' ? '' : '&') . $name . '=' . rawurlencode($value);
        }

        if ($this->authorizationRequest->state !== '') {
            $query .= '&state=' . rawurlencode($this->authorizationRequest->state);
        }

        $uri = $this->authorizationRequest->redirectUri;

        return $responseWriter->redirect(303, $uri . (str_contains($uri, '?') ? '&' : '?') . $query);
    }
}
