<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use JsonSerializable;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * An API answered with JSON: a status and a body that serializes itself.
 */
final readonly class JsonResponse implements ApiResponse
{
    /**
     * @param JsonSerializable $jsonSerializable The body; its `jsonSerialize()` gives the JSON value.
     * @param int              $status           The status.
     */
    public function __construct(
        private JsonSerializable $jsonSerializable,
        private int $status = 200,
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
        return $responseWriter->json($this->status, $this->jsonSerializable);
    }
}
