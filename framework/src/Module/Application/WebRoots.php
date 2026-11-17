<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Contracts\PageLayouts;
use AIC\Config\Domain\AppConfig;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Contracts\PublicAssets;
use AIC\HttpClient\Application\Outbound;

/** Typed roots exposed to generated web wiring, without the HTTP wrapper dependencies. */
final readonly class WebRoots
{
    /**
     * @param AppConfig        $appConfig       The application settings.
     * @param Database         $database        The lazy application database.
     * @param EnvelopeCodec    $envelopeCodec   Seals and opens form values.
     * @param ResponseBuilder  $responseBuilder Makes middleware responses.
     * @param Plugins          $plugins         The composed modules' plugins.
     * @param PublicAssets     $publicAssets    The compiled public asset catalog.
     * @param PageLayouts|null $pageLayouts     The selected compiled theme, when explicitly declared.
     * @param WebWiring        $webWiring       The modules' wiring, which names their schedules and protected prefixes.
     * @param string           $bootedAt        The request's boot time.
     * @param string           $root            The application root directory.
     * @param Outbound         $outbound        The client, pool and upstreams for outbound calls (#305).
     */
    public function __construct(
        public AppConfig $appConfig,
        public Database $database,
        public EnvelopeCodec $envelopeCodec,
        public ResponseBuilder $responseBuilder,
        public Plugins $plugins,
        public PublicAssets $publicAssets,
        public ?PageLayouts $pageLayouts,
        public WebWiring $webWiring,
        public string $bootedAt,
        public string $root,
        public Outbound $outbound,
    ) {
    }
}
