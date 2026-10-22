<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use AIC\Tessera\Domain\TesseraRequest;
use LogicException;
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
     * @param FormTokens     $formTokens     Issues the visitor's CSRF token, which the document's head carries for Tessera.
     */
    public function __construct(
        private Page $page,
        private ResponseWriter $responseWriter,
        private Blocks $blocks,
        private FormTokens $formTokens,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pageResponse = $this->page->page($request);

        if ($pageResponse instanceof PageDocument) {
            $tesseraRequest = new TesseraRequest(
                $request->getHeaderLine(TesseraRequest::REQUEST),
                $request->getHeaderLine(TesseraRequest::PROTOCOL),
                $request->getHeaderLine(TesseraRequest::TARGETS),
                $request->getHeaderLine(TesseraRequest::LAYOUT),
            );

            return $pageResponse->withBlocks($this->blocks, $request)
                ->withCsrfToken($this->formTokens->issue($request)->value())
                ->answer($tesseraRequest)
                ->writeTo($this->responseWriter);
        }

        return $pageResponse->writeTo($this->responseWriter);
    }
}
