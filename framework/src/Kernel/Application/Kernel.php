<?php

declare(strict_types=1);

namespace AIC\Kernel\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\MalformedInputException;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Sapi\Contracts\SapiRequest;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Request in, response out, across the SAPI boundary: the kernel turns a SAPI
 * request into a PSR-7 request (through the RequestExtractor contract, so it
 * stays above the SAPI adapter) and hands it to the PSR-15 stack.
 *
 * It is the one place a request becomes a response, so it holds the boundaries:
 * input that does not fit the field shapes answers a plain 400, and anything
 * else uncaught a plain 500, so no error escapes into the SAPI. Neither repeats
 * the input, and no framework class holds markup (#162).
 */
final readonly class Kernel
{
    /**
     * The boundary responses' content type.
     */
    private const string PLAIN_TEXT = 'text/plain; charset=utf-8';

    /**
     * @param RequestExtractor        $requestExtractor Makes the PSR-7 request.
     * @param RequestHandlerInterface $requestHandler   The stack that answers it.
     * @param ResponseBuilder         $responseBuilder  Makes the boundary responses.
     */
    public function __construct(
        private RequestExtractor $requestExtractor,
        private RequestHandlerInterface $requestHandler,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param SapiRequest $sapiRequest One request, as the SAPI gave it.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The response: the stack's, a 400 or a 500.
     */
    public function serve(SapiRequest $sapiRequest): ResponseInterface
    {
        try {
            return $this->requestHandler->handle($this->requestExtractor->extract($sapiRequest));
        } catch (MalformedInputException) {
            return $this->responseBuilder->create(400, self::PLAIN_TEXT, 'Bad Request');
        } catch (Throwable) {
            return $this->responseBuilder->create(500, self::PLAIN_TEXT, 'Internal Server Error');
        }
    }
}
