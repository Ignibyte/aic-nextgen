<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Contracts\PageLayouts;
use AIC\Config\Domain\AppConfig;
use AIC\Container\Exception\ContainerException;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Contracts\PublicAssets;
use AIC\Schedule\Domain\Schedule;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What the generated web wiring (`generated/`, #92, #93) builds the modules'
 * handlers and middleware from: the roots a module's constructors may ask for,
 * and one wrapper per HTTP kind.
 *
 * The roots are what the app kernel holds and the build cannot make: the app's
 * settings, the database, the envelope codec, the response builder, the plugins
 * by type, the modules' schedules and protected prefixes, the boot time and the
 * app's root. Which of them a given set of modules uses depends on the modules,
 * so every member is framework API.
 *
 * @api
 */
final readonly class HandlerContext
{
    /**
     * @param HttpHandlers $httpHandlers The Page, Api and Stream wrappers.
     * @param WebRoots     $webRoots     The grouped typed roots for generated constructors.
     */
    public function __construct(
        private HttpHandlers $httpHandlers,
        private WebRoots $webRoots,
    ) {
    }

    /**
     * @param Page $page A Page controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function page(Page $page): RequestHandlerInterface
    {
        return $this->httpHandlers->page($page);
    }

    /**
     * @param Api $api An Api controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function api(Api $api): RequestHandlerInterface
    {
        return $this->httpHandlers->api($api);
    }

    /**
     * @param Stream $stream A Stream controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function stream(Stream $stream): RequestHandlerInterface
    {
        return $this->httpHandlers->stream($stream);
    }

    /**
     * @return AppConfig The app's settings.
     */
    public function appConfig(): AppConfig
    {
        return $this->webRoots->appConfig;
    }

    /**
     * @return Database The database.
     */
    public function database(): Database
    {
        return $this->webRoots->database;
    }

    /**
     * @return EnvelopeCodec The envelope codec.
     */
    public function envelopeCodec(): EnvelopeCodec
    {
        return $this->webRoots->envelopeCodec;
    }

    /**
     * @return ResponseBuilder The response builder.
     */
    public function responseBuilder(): ResponseBuilder
    {
        return $this->webRoots->responseBuilder;
    }

    /**
     * @param string $type A plugin type.
     *
     * @throws ContainerException When a registered service cannot be resolved.
     *
     * @return list<object> The plugins tagged under it, in registration order.
     */
    public function tagged(string $type): array
    {
        return $this->webRoots->plugins->tagged($type);
    }

    /**
     * @return list<Schedule> Every module's schedules.
     */
    public function schedules(): array
    {
        return $this->webRoots->schedules;
    }

    /**
     * @return list<string> Every module's protected path prefixes.
     */
    public function protectedPrefixes(): array
    {
        return $this->webRoots->protectedPrefixes;
    }

    /**
     * @return string When the app booted to serve this request.
     */
    public function bootedAt(): string
    {
        return $this->webRoots->bootedAt;
    }

    /**
     * @return string The app's root directory.
     */
    public function root(): string
    {
        return $this->webRoots->root;
    }

    /** @return PublicAssets The compiled public catalog available to layout and component composition. */
    public function assets(): PublicAssets
    {
        return $this->webRoots->publicAssets;
    }

    /**
     * @throws ContainerException When the selected-theme root cannot be resolved.
     *
     * @return PageLayouts The generated selected-theme factory.
     */
    public function layouts(): PageLayouts
    {
        return $this->webRoots->pageLayouts ?? throw new ContainerException('The generated presentation declares no theme.');
    }
}
