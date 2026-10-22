<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Application\Renderer;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use AIC\Secret\Application\HmacSigner;
use AIC\Secret\Domain\AppSecret;
use AIC\Session\Domain\StringMapJson;

/**
 * Builds the web side's handler context (#93): the modules' plugins in a
 * frozen container, the blocks among them, the page handlers around those, the
 * envelope codec from the app secret, and the modules' schedules and protected
 * prefixes.
 */
final readonly class HandlerContexts
{
    /**
     * @param AppConfig       $appConfig       The app's settings.
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     * @param Database        $database        The database.
     * @param AppSecret       $appSecret       Signs the forms' envelopes.
     * @param string          $bootedAt        When the app booted to serve this request, ISO 8601 in UTC.
     * @param string          $root            The app's root directory.
     */
    public function __construct(
        private AppConfig $appConfig,
        private ResponseBuilder $responseBuilder,
        private Database $database,
        private AppSecret $appSecret,
        private string $bootedAt,
        private string $root,
    ) {
    }

    /**
     * @param WebWiring $webWiring The modules' plugins, schedules, protected prefixes and templates.
     *
     * @return HandlerContext The context.
     */
    public function create(WebWiring $webWiring): HandlerContext
    {
        $plugins = new Plugins($webWiring instanceof PluginWiring ? $webWiring : null);
        $psr7ResponseWriter = new Psr7ResponseWriter($this->responseBuilder, new Renderer($webWiring->templates()));

        return new HandlerContext(
            new PageHandlers($psr7ResponseWriter, $plugins->blocks()),
            $psr7ResponseWriter,
            $this->responseBuilder,
            $this->appConfig,
            $this->database,
            new EnvelopeCodec(new HmacSigner($this->appSecret), new StringMapJson()),
            $plugins,
            $webWiring->schedules(),
            $webWiring->protectedPrefixes(),
            $this->bootedAt,
            $this->root,
        );
    }
}
