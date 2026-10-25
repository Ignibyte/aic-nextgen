<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Block\Contracts\Blocks;
use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Application\Renderer;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Controller\Application\StreamHandler;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\Stream;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Server\RequestHandlerInterface;

/** Compose the three HTTP wrappers separately from the generated constructor roots. */
final readonly class HttpHandlers
{
    /** The page wrapper with the application's blocks. */
    private PageHandlers $pageHandlers;

    /** The shared kind response writer. */
    private ResponseWriter $responseWriter;

    /**
     * @param ResponseBuilder    $responseBuilder    The response and file factories.
     * @param ComponentTemplates $componentTemplates The compiled component templates.
     * @param Blocks             $blocks             The modules' composed blocks.
     */
    public function __construct(
        ResponseBuilder $responseBuilder,
        ComponentTemplates $componentTemplates,
        Blocks $blocks,
    ) {
        $fileResponses = new FileResponses($responseBuilder);
        $this->responseWriter = new Psr7ResponseWriter($responseBuilder, new Renderer($componentTemplates), $fileResponses);
        $this->pageHandlers = new PageHandlers($this->responseWriter, $blocks);
    }

    /**
     * @param Page $page A Page controller.
     *
     * @return RequestHandlerInterface Its page wrapper.
     */
    public function page(Page $page): RequestHandlerInterface
    {
        return $this->pageHandlers->handler($page);
    }

    /**
     * @param Api $api An Api controller.
     *
     * @return RequestHandlerInterface Its API wrapper.
     */
    public function api(Api $api): RequestHandlerInterface
    {
        return new ApiHandler($api, $this->responseWriter);
    }

    /**
     * @param Stream $stream A Stream controller.
     *
     * @return RequestHandlerInterface Its stream wrapper.
     */
    public function stream(Stream $stream): RequestHandlerInterface
    {
        return new StreamHandler($stream, $this->responseWriter);
    }
}
