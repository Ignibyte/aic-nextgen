<?php

declare(strict_types=1);

namespace AIC\Kernel\Infrastructure;

use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Application\ResponseFactory;
use AIC\Http\Application\ServerRequestFactory;
use AIC\Http\Application\StreamFactory;
use AIC\Http\Application\UriFactory;
use AIC\Http\Domain\InputParser;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Sapi\Contracts\SapiSink;
use AIC\Sapi\Infrastructure\SapiRequestExtractor;
use AIC\Sapi\Infrastructure\SapiResponseEmitter;

/**
 * The framework's SAPI and HTTP wiring, built once per worker from its sink: the
 * request extractor, the response emitter, and the response builder an app's
 * handlers and the kernel use.
 *
 * Wiring is code in AIC: constructors and readonly properties, typed throughout
 * and compiled natively. An app's composition root builds this, then its own
 * services on top.
 */
final readonly class SapiServices
{
    /**
     * Makes the PSR-7 request from a SAPI request.
     */
    private RequestExtractor $requestExtractor;

    /**
     * Sends a response through the sink.
     */
    private SapiResponseEmitter $sapiResponseEmitter;

    /**
     * Makes responses with a content type and a body.
     */
    private ResponseBuilder $responseBuilder;

    /**
     * @param SapiSink $sapiSink Where responses go out: the worker's SAPI calls.
     */
    public function __construct(SapiSink $sapiSink)
    {
        $uriFactory = new UriFactory();
        $streamFactory = new StreamFactory();
        $this->requestExtractor = new SapiRequestExtractor(
            new ServerRequestFactory($uriFactory, $streamFactory),
            $uriFactory,
            $streamFactory,
            new InputParser(),
        );
        $this->sapiResponseEmitter = new SapiResponseEmitter($sapiSink);
        $this->responseBuilder = new ResponseBuilder(new ResponseFactory($streamFactory), $streamFactory);
    }

    /**
     * @return RequestExtractor Makes the PSR-7 request from a SAPI request.
     */
    public function requestExtractor(): RequestExtractor
    {
        return $this->requestExtractor;
    }

    /**
     * @return SapiResponseEmitter Sends a response through the sink.
     */
    public function sapiResponseEmitter(): SapiResponseEmitter
    {
        return $this->sapiResponseEmitter;
    }

    /**
     * @return ResponseBuilder Makes responses with a content type and a body.
     */
    public function responseBuilder(): ResponseBuilder
    {
        return $this->responseBuilder;
    }
}
