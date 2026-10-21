<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Form\Application\FormTokens;

/**
 * Wraps each Page controller in its PageHandler, with the app's response
 * writer and its blocks, so the composition root names them once.
 */
final readonly class PageHandlers
{
    /**
     * @param ResponseWriter $responseWriter Writes the pages' responses as PSR-7.
     * @param Blocks         $blocks         The app's blocks.
     * @param FormTokens     $formTokens     Issues the visitor's CSRF token for the documents' heads.
     */
    public function __construct(
        private ResponseWriter $responseWriter,
        private Blocks $blocks,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param Page $page A Page controller.
     *
     * @return PageHandler It, as a PSR-15 handler.
     */
    public function handler(Page $page): PageHandler
    {
        return new PageHandler($page, $this->responseWriter, $this->blocks, $this->formTokens);
    }
}
