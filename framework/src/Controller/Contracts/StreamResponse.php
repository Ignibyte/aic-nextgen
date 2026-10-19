<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use Psr\Http\Message\ResponseInterface;

/**
 * What a Stream controller returns. Sealed: only the response shapes in
 * AIC\Controller\Domain implement it (a phpat guard), and each writes itself
 * through the ResponseWriter.
 */
interface StreamResponse
{
    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface;
}
