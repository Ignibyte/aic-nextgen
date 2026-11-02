<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * An Api's XML answer (#233): a typed `XmlDocument` and a status, written
 * as `application/xml; charset=utf-8`. It takes no string of markup.
 */
final readonly class XmlResponse implements ApiResponse
{
    /**
     * @param XmlDocument $xmlDocument The document.
     * @param int         $status      The status.
     */
    public function __construct(
        private XmlDocument $xmlDocument,
        private int $status = 200,
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
        return $responseWriter->xml($this->status, $this->xmlDocument->serialized());
    }
}
