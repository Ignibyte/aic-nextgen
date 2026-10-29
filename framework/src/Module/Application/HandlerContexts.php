<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Config\Domain\AppConfig;
use AIC\Container\Exception\ContainerException;
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
     * @param WebWiring       $webWiring       The modules' plugins, schedules and protected prefixes.
     * @param WebPresentation $webPresentation The already composed build-owned presentation catalogs.
     *
     * @throws ContainerException When a registered service cannot be resolved.
     *
     * @return HandlerContext The context.
     */
    public function create(WebWiring $webWiring, WebPresentation $webPresentation): HandlerContext
    {
        $plugins = new Plugins($webWiring instanceof PluginWiring ? $webWiring : null);
        $roots = $this->roots($webWiring, $plugins, $webPresentation);

        return (new ModuleLayers())->context($webWiring, $roots, $this->responseBuilder, $webPresentation->componentTemplates);
    }

    /** @return string The inert application root, available before any module provider runs. */
    public function root(): string
    {
        return $this->root;
    }

    /** @return int The largest request body the app takes (#196), before any module provider runs. */
    public function maxBodyBytes(): int
    {
        return $this->appConfig->maxBodyBytes;
    }

    /**
     * @param WebWiring       $webWiring       The compiled request roots.
     * @param Plugins         $plugins         The modules' already composed plugins.
     * @param WebPresentation $webPresentation The already composed presentation catalogs.
     *
     * @return WebRoots The typed constructor roots.
     */
    private function roots(WebWiring $webWiring, Plugins $plugins, WebPresentation $webPresentation): WebRoots
    {
        return new WebRoots(
            $this->appConfig,
            $this->database,
            new EnvelopeCodec(new HmacSigner($this->appSecret), new StringMapJson()),
            $this->responseBuilder,
            $plugins,
            $webPresentation->publicAssets,
            $webPresentation->pageLayouts,
            $webWiring->schedules(),
            $webWiring->protectedPrefixes(),
            $this->bootedAt,
            $this->root,
        );
    }
}
