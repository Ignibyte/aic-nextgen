<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Component\Contracts\Layout;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\SignalPatch;
use Override;
use Psr\Http\Message\ResponseInterface;

use function array_map;

/**
 * A page answered as Tessera fragments (#70, protocol 5.3): the title, the
 * tracked assets, one template per target and the page's signal patches
 * (#253), made by PageDocument::answer()
 * for a Tessera visit or refresh, never by a page itself.
 */
final readonly class PageFragments implements PageResponse
{
    /**
     * @param int               $status        The page's status.
     * @param Layout            $layout        The page.
     * @param list<string>      $targets       The regions and component paths to answer, in order; at least one.
     * @param bool              $removing      Whether a component path the page no longer renders is answered removed
     *                                         (#247).
     * @param list<SignalPatch> $signalPatches The page's signal patches, in order (#253).
     */
    public function __construct(
        private int $status,
        private Layout $layout,
        private array $targets,
        private bool $removing = false,
        private array $signalPatches = [],
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
        return $responseWriter->fragments($this->status, $this->layout, $this->targets, $this->removing, array_map(
            static fn (SignalPatch $signalPatch): array => $signalPatch->scripted(),
            $this->signalPatches,
        ));
    }
}
