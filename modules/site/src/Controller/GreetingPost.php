<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Site\Form\GreetingForm;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_string;
use function rawurlencode;
use function trim;

/**
 * The greeting form's target, POST /hello: a name answers 303 to its greeting;
 * a blank one renders the form again in place, with its error, as 422.
 */
final readonly class GreetingPost implements Page
{
    /**
     * @param PageDocuments $pageDocuments The document around the page.
     * @param GreetingForm  $greetingForm  The form, shown again on an error.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private GreetingForm $greetingForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The redirect, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $body = $serverRequest->getParsedBody();
        $name = is_array($body) && array_key_exists('name', $body) && is_string($body['name'])
            ? trim($body['name'])
            : '';

        if ($name === '') {
            return $this->pageDocuments->content([$this->greetingForm->form($serverRequest, '', 'A name is required.')], 422);
        }

        return new PageRedirect('/hello/' . rawurlencode($name));
    }
}
