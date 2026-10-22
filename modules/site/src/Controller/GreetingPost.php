<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Site\Form\GreetingForm;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

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
     * @param HtmlLayout   $htmlLayout   The document around the page.
     * @param GreetingForm $greetingForm The form, shown again on an error.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private GreetingForm $greetingForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     *
     * @return PageResponse The redirect, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $body = $serverRequest->getParsedBody();
        $field = is_array($body)
            ? $body['name'] ?? ''
            : '';
        $name = trim(is_string($field) ? $field : '');

        if ($name === '') {
            return $this->htmlLayout->content([$this->greetingForm->form($serverRequest, '', 'A name is required.')], 422);
        }

        return new PageRedirect('/hello/' . rawurlencode($name));
    }
}
