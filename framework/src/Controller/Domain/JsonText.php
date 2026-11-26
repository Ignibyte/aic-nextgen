<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * An Api's JSON answer already encoded (#332), as `application/json`: for an
 * answer that carries a stored JSON value as it was written, such as the MCP
 * server's replay of a call id's result. Build one only from JSON your code
 * encoded; a value to encode is a `JsonResponse`.
 *
 * @api
 */
final readonly class JsonText implements ApiResponse
{
    /**
     * @param string $json   The body, a JSON document.
     * @param int    $status The status.
     */
    public function __construct(
        private string $json,
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
        return $responseWriter->encoded($this->status, 'application/json', $this->json);
    }
}
