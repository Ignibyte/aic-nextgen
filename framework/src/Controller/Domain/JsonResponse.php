<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use JsonSerializable;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An API answered with JSON: a status and a body that serializes itself. Given the request it answers, a 200 to a
 * GET or HEAD carries a strong ETag of its body and `private, no-cache`, and a matching `If-None-Match` gets 304
 * (#269); without it, the answer is `private, no-store` with no validator.
 */
final readonly class JsonResponse implements ApiResponse
{
    /**
     * @param JsonSerializable            $jsonSerializable The body; its `jsonSerialize()` gives the JSON value.
     * @param int                         $status           The status.
     * @param ServerRequestInterface|null $serverRequest    The request whose `If-None-Match` it answers, to opt in to
     *                                                      its ETag and 304 (#269); null for none.
     */
    public function __construct(
        private JsonSerializable $jsonSerializable,
        private int $status = 200,
        private ?ServerRequestInterface $serverRequest = null,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->json($this->status, $this->jsonSerializable, $this->serverRequest);
    }
}
