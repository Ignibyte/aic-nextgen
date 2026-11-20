<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * An Api's `202 Accepted` with no body (#316): the request was taken and there
 * is nothing to answer, as MCP's Streamable HTTP requires for a notification.
 */
final readonly class ApiAccepted implements ApiResponse
{
    /**
     * @param ResponseWriter $responseWriter The writer for this request.
     *
     * @return ResponseInterface The `202`.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->accepted();
    }
}
