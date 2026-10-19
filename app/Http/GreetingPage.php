<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;
use function is_string;

/**
 * The greeting at `/hello/{name}`: the route's parameter reaches the controller
 * as a request attribute.
 */
final readonly class GreetingPage implements Page
{
    /**
     * @param HtmlLayout $htmlLayout The document around the page.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `name` attribute.
     *
     * @return PageResponse The greeting.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $name = $serverRequest->getAttribute('name');

        return new PageDocument($this->htmlLayout->document(
            '<p id="greeting">Hello, ' . htmlspecialchars(is_string($name) ? $name : '') . '</p>',
        ));
    }
}
