<?php

declare(strict_types=1);

namespace App\Account\Service;

use AIC\Access\Application\Actors;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Application\Authorizations;
use AIC\OAuth\Contracts\OAuthRefusedException;
use App\Account\Form\ConsentForm;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authorization endpoint for a signed-in person (#331), for its GET page
 * and its POST decision: the request is checked (again, for the post, from the
 * form) by `Authorizations`; an unknown client or redirect URI is refused here,
 * 400; another fault goes back to the client with its error. A GET shows who
 * asks and what it would hold; a post allows, sending a code for the scopes the
 * person can grant, or denies.
 */
final readonly class Consent
{
    /**
     * @param PageDocuments  $pageDocuments  Makes the pages.
     * @param Authorizations $authorizations Checks the request and answers it.
     * @param Actors         $actors         The person.
     * @param ConsentForm    $consentForm    The pages' parts.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private Authorizations $authorizations,
        private Actors $actors,
        private ConsentForm $consentForm = new ConsentForm(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The authorization request, or the consent form's post.
     *
     * @throws QueryException When the clients, the roles or the code cannot be read or written.
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse The consent page, 303 back to the client, or the refusal.
     */
    public function answer(ServerRequestInterface $serverRequest): PageResponse
    {
        try {
            $authorizationRequest = $this->authorizations->read($serverRequest);
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->pageDocuments->content($this->consentForm->refused($oAuthRefusedException->description), 400, 'Authorization');
        }

        if ($authorizationRequest->refusal !== '') {
            return $this->authorizations->refuse($authorizationRequest, $authorizationRequest->refusal);
        }

        $actor = $this->actors->actor($serverRequest);

        if ($serverRequest->getMethod() === 'POST') {
            return $this->authorizations->allowed($serverRequest)
                ? $this->authorizations->allow($authorizationRequest, $actor)
                : $this->authorizations->refuse($authorizationRequest, 'access_denied');
        }

        $grantable = $this->authorizations->grantable($authorizationRequest, $actor);
        $components = $this->consentForm->consent($serverRequest, $authorizationRequest, $grantable);

        return $this->pageDocuments->content($components, 200, 'Authorization');
    }
}
