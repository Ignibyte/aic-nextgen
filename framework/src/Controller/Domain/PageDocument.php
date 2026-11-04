<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Block\Contracts\Blocks;
use AIC\Component\Contracts\Layout;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\SignalPatch;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;

/**
 * A page answered as a whole HTML document, its layout rendered with the
 * regions the page filled (#37): 200; 400 when a posted form's signed
 * envelope was altered (#43); 403 when the signed-in user may not do what
 * was asked (#197); 404 when what it shows does not exist; 409 for a write
 * that conflicts; or 422 when a form posted to it is rendered again in place
 * with its errors. A Tessera fragment answer of it also carries the signal
 * patches the page added (#253); the whole document does not.
 */
final readonly class PageDocument implements PageResponse
{
    /**
     * The statuses a document may carry.
     */
    private const array STATUSES = [200, 400, 403, 404, 409, 422];

    /**
     * @param Layout            $layout        The document, with its regions filled.
     * @param int               $status        200, 400 for an altered form, 403 for a user not allowed, 404 for a
     *                                         missing thing, 409 for a write that conflicts, or 422 for a form shown
     *                                         again with its errors.
     * @param list<SignalPatch> $signalPatches What a fragment answer of it patches into the page's signals, in order.
     * @param bool              $prefetchable  Whether it answers `private, no-cache`, so Tessera may use a hover
     *                                         prefetch of it (#254); `private, no-store` otherwise.
     *
     * @throws InvalidArgumentException When the status is none of these, or a filled region is one the layout does not place.
     */
    public function __construct(
        private Layout $layout,
        private int $status = 200,
        private array $signalPatches = [],
        private bool $prefetchable = false,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A page document answers 200, 400, 403, 404, 409 or 422; got ' . $status . '.');
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
        return $responseWriter->document($this->status, $this->layout, $this->prefetchable);
    }

    /**
     * @param Blocks                 $blocks        The app's blocks.
     * @param ServerRequestInterface $serverRequest The request the document answers.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The same document, its layout holding the matching blocks' components.
     */
    public function withBlocks(Blocks $blocks, ServerRequestInterface $serverRequest): self
    {
        $regions = $blocks->place($this->layout->regions(), $this->layout->names(), $serverRequest);

        return new self($this->layout->withRegions($regions), $this->status, $this->signalPatches, $this->prefetchable);
    }

    /**
     * @param string $csrfToken The visitor's CSRF token.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The same document, its layout carrying the token for Tessera (#70).
     */
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->layout->withCsrfToken($csrfToken), $this->status, $this->signalPatches, $this->prefetchable);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The same document, answered `private, no-cache` so a hover prefetch of it is used (#254): for a
     *              page that may be shown from an answer a few seconds old.
     */
    public function prefetchable(): self
    {
        return new self($this->layout, $this->status, $this->signalPatches, true);
    }

    /**
     * @param SignalPatch $signalPatch A patch for the page's signals.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The same document, a fragment answer of it carrying the patch after those added before (#253).
     */
    public function withSignalPatch(SignalPatch $signalPatch): self
    {
        return new self($this->layout, $this->status, [...$this->signalPatches, $signalPatch], $this->prefetchable);
    }

    /**
     * @param TesseraRequest $tesseraRequest What the request asks of Tessera's server side.
     *
     * @throws InvalidArgumentException When a path the request named for an anchor is not a target.
     *
     * @return PageResponse Fragments for a Tessera visit or refresh whose targets all exist under this layout, else this document (#70).
     */
    public function answer(TesseraRequest $tesseraRequest): PageResponse
    {
        $targets = $tesseraRequest->targets($this->layout);

        return $targets === []
            ? $this
            : new PageFragments(
                $this->status,
                $this->layout,
                $targets,
                $tesseraRequest->removesGone(),
                $this->scoped($tesseraRequest),
                $this->prefetchable,
            );
    }

    /**
     * @param TesseraRequest $tesseraRequest What the request asks of Tessera's server side.
     *
     * @throws InvalidArgumentException When a path the request named is not a target.
     *
     * @return list<SignalPatch> The page's patches as a fragment answer writes them (#260): one scoped `@NAME`
     *                           rescoped to the path the request named with that anchor, and left out when it named
     *                           none, since it would scope nothing the answer holds.
     */
    private function scoped(TesseraRequest $tesseraRequest): array
    {
        $scoped = [];

        foreach ($this->signalPatches as $signalPatch) {
            if (!$signalPatch->anchored()) {
                $scoped[] = $signalPatch;

                continue;
            }

            $path = $tesseraRequest->anchorPath($signalPatch->scope);

            if ($path === '') {
                continue;
            }

            $scoped[] = $signalPatch->scopedTo($path);
        }

        return $scoped;
    }
}
