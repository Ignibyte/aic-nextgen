<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Domain\PageDocument;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs a Page controller as a PSR-15 handler: when it answers a document, the
 * app's blocks are placed in its regions for the request's path; then the
 * response writes itself. A redirect has no regions.
 */
final readonly class PageHandler implements RequestHandlerInterface
{
    /**
     * @param Page           $page           The controller.
     * @param ResponseWriter $responseWriter Writes its response as PSR-7.
     * @param Blocks         $blocks         The app's blocks.
     */
    public function __construct(
        private Page $page,
        private ResponseWriter $responseWriter,
        private Blocks $blocks,
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
        $pageResponse = $this->page->page($request);

        if ($pageResponse instanceof PageDocument) {
            return $pageResponse->withBlocks($this->blocks, $request)
                ->writeTo($this->responseWriter);
        }

        return $pageResponse->writeTo($this->responseWriter);
    }
}
