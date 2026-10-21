<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Block\Contracts\Blocks;
use AIC\Component\Contracts\Layout;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;

/**
 * A page answered as a whole HTML document, its layout rendered with the
 * regions the page filled (#37): 200; 400 when a posted form's signed
 * envelope was altered (#43); 404 when what it shows does not exist; or 422
 * when a form posted to it is rendered again in place with its errors.
 */
final readonly class PageDocument implements PageResponse
{
    /**
     * The statuses a document may carry.
     */
    private const array STATUSES = [200, 400, 404, 422];

    /**
     * @param Layout $layout The document, with its regions filled.
     * @param int    $status 200, 400 for an altered form, 404 for a missing thing, or 422 for a form shown again with its errors.
     *
     * @throws InvalidArgumentException When the status is none of these, or a filled region is one the layout does not place.
     */
    public function __construct(
        private Layout $layout,
        private int $status = 200,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A page document answers 200, 400, 404 or 422; got ' . $status . '.');
        }

        foreach ($layout->regions()->names() as $name) {
            if (!in_array($name, $layout->names(), true)) {
                throw new InvalidArgumentException('The layout places no region "' . $name . '".');
            }
        }
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->document($this->status, $this->layout->render()->markup());
    }

    /**
     * @param Blocks                 $blocks        The app's blocks.
     * @param ServerRequestInterface $serverRequest The request the document answers.
     *
     * @return self The same document, its layout holding the matching blocks' components.
     */
    public function withBlocks(Blocks $blocks, ServerRequestInterface $serverRequest): self
    {
        $regions = $blocks->place($this->layout->regions(), $this->layout->names(), $serverRequest);

        return new self($this->layout->withRegions($regions), $this->status);
    }

    /**
     * @param string $csrfToken The visitor's CSRF token.
     *
     * @return self The same document, its layout carrying the token for Tessera (#70).
     */
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->layout->withCsrfToken($csrfToken), $this->status);
    }

    /**
     * @param TesseraRequest $tesseraRequest What the request asks of Tessera's server side.
     *
     * @return PageResponse Fragments for a Tessera visit or refresh whose targets all exist under this layout, else this document (#70).
     */
    public function answer(TesseraRequest $tesseraRequest): PageResponse
    {
        $fragments = $tesseraRequest->fragments($this->layout);

        return $fragments === ''
            ? $this
            : new PageFragments($this->status, $fragments);
    }
}
