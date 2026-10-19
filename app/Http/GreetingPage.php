<?php

declare(strict_types=1);

namespace App\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function htmlspecialchars;
use function is_string;

/**
 * The greeting at `/hello/{name}`: it shows that a route's parameter reaches the
 * handler as a request attribute.
 */
final readonly class GreetingPage implements RequestHandlerInterface
{
    /**
     * @param HtmlResponder $htmlResponder The document and response.
     */
    public function __construct(
        private HtmlResponder $htmlResponder,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request, with the `name` attribute.
     *
     * @return ResponseInterface The greeting.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute('name');

        return $this->htmlResponder->respond(
            '<p id="greeting">Hello, ' . htmlspecialchars(is_string($name) ? $name : '') . '</p>',
        );
    }
}
