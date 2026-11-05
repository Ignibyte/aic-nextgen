<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An Api's XML answer (#233): a typed `XmlDocument` and a status, written
 * as `application/xml; charset=utf-8`. It takes no string of markup. Given
 * the request it answers, a 200 to a GET or HEAD carries a strong ETag of its
 * body and `private, no-cache`, and a matching `If-None-Match` gets 304 (#269).
 */
final readonly class XmlResponse implements ApiResponse
{
    /**
     * @param XmlDocument                 $xmlDocument   The document.
     * @param int                         $status        The status.
     * @param ServerRequestInterface|null $serverRequest The request whose `If-None-Match` it answers, to opt in to its
     *                                                   ETag and 304 (#269); null for none.
     */
    public function __construct(
        private XmlDocument $xmlDocument,
        private int $status = 200,
        private ?ServerRequestInterface $serverRequest = null,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes it.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->xml($this->status, $this->xmlDocument->serialized(), $this->serverRequest);
    }
}
