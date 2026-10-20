<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;

/**
 * Wraps each Page controller in its PageHandler, with the app's response
 * writer and its blocks, so the composition root names them once.
 */
final readonly class PageHandlers
{
    /**
     * @param ResponseWriter $responseWriter Writes the pages' responses as PSR-7.
     * @param Blocks         $blocks         The app's blocks.
     */
    public function __construct(
        private ResponseWriter $responseWriter,
        private Blocks $blocks,
    ) {
    }

    /**
     * @param Page $page A Page controller.
     *
     * @return PageHandler It, as a PSR-15 handler.
     */
    public function handler(Page $page): PageHandler
    {
        return new PageHandler($page, $this->responseWriter, $this->blocks);
    }
}
