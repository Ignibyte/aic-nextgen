<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * Nothing changed (#193, protocol 6): a `204` Tessera leaves the page alone
 * for. A Page answers it to a refresh of one component whose sealed version
 * still holds (`TesseraProps::unchanged()`), before it builds anything.
 */
final readonly class PageUnchanged implements PageResponse
{
    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->unchanged();
    }
}
