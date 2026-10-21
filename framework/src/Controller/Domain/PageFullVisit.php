<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * A redirect the browser must load from scratch (#71, protocol 5.5): after
 * logout, or to a page Tessera cannot update in place. A plain request gets a
 * `303`; Tessera gets `Tessera-Full-Visit` and loads the URL itself.
 */
final readonly class PageFullVisit implements PageResponse
{
    /**
     * @param string $location Where to go, a path on this site.
     */
    public function __construct(
        private string $location,
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
        return $responseWriter->fullVisit($this->location);
    }
}
