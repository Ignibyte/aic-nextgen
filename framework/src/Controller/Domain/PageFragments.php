<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * A page answered as Tessera fragments (#70, protocol 5.3): the title, the
 * tracked assets and one template per target, made by PageDocument::answer()
 * for a Tessera visit or refresh, never by a page itself.
 */
final readonly class PageFragments implements PageResponse
{
    /**
     * @param int    $status The page's status.
     * @param string $body   The fragment response's body.
     */
    public function __construct(
        private int $status,
        private string $body,
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
        return $responseWriter->fragments($this->status, $this->body);
    }
}
