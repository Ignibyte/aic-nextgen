<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs a Page controller as a PSR-15 handler: its response writes itself.
 */
final readonly class PageHandler implements RequestHandlerInterface
{
    /**
     * @param Page           $page           The controller.
     * @param ResponseWriter $responseWriter Writes its response as PSR-7.
     */
    public function __construct(
        private Page $page,
        private ResponseWriter $responseWriter,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->page->page($request)->writeTo($this->responseWriter);
    }
}
