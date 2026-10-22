<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Application\StreamHandler;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\Stream;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
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
     * @param PageHandlers    $pageHandlers      Wraps a Page with the response writer and the blocks.
     * @param ResponseWriter  $responseWriter    Writes the Api and Stream responses.
     * @param ResponseBuilder $responseBuilder   Makes PSR-7 responses, for middleware.
     * @param AppConfig       $appConfig         The app's settings.
     * @param Database        $database          The database.
     * @param EnvelopeCodec   $envelopeCodec     Seals and opens the forms' signed envelopes.
     * @param Plugins         $plugins           The plugins, by type.
     * @param list<Schedule>  $schedules         Every module's schedules.
     * @param list<string>    $protectedPrefixes Every module's protected path prefixes.
     * @param string          $bootedAt          When the app booted to serve this request, ISO 8601 in UTC.
     * @param string          $root              The app's root directory.
     */
    public function __construct(
        private PageHandlers $pageHandlers,
        private ResponseWriter $responseWriter,
        private ResponseBuilder $responseBuilder,
        private AppConfig $appConfig,
        private Database $database,
        private EnvelopeCodec $envelopeCodec,
        private Plugins $plugins,
        private array $schedules,
        private array $protectedPrefixes,
        private string $bootedAt,
        private string $root,
    ) {
    }

    /**
     * @param Page $page A Page controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function page(Page $page): RequestHandlerInterface
    {
        return $this->pageHandlers->handler($page);
    }

    /**
     * @param Api $api An Api controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function api(Api $api): RequestHandlerInterface
    {
        return new ApiHandler($api, $this->responseWriter);
    }

    /**
     * @param Stream $stream A Stream controller.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    public function stream(Stream $stream): RequestHandlerInterface
    {
        return new StreamHandler($stream, $this->responseWriter);
    }

    /**
     * @return AppConfig The app's settings.
     */
    public function appConfig(): AppConfig
    {
        return $this->appConfig;
    }

    /**
     * @return Database The database.
     */
    public function database(): Database
    {
        return $this->database;
    }

    /**
     * @return EnvelopeCodec The envelope codec.
     */
    public function envelopeCodec(): EnvelopeCodec
    {
        return $this->envelopeCodec;
    }

    /**
     * @return ResponseBuilder The response builder.
     */
    public function responseBuilder(): ResponseBuilder
    {
        return $this->responseBuilder;
    }

    /**
     * @param string $type A plugin type.
     *
     * @return list<object> The plugins tagged under it, in registration order.
     */
    public function tagged(string $type): array
    {
        return $this->plugins->tagged($type);
    }

    /**
     * @return list<Schedule> Every module's schedules.
     */
    public function schedules(): array
    {
        return $this->schedules;
    }

    /**
     * @return list<string> Every module's protected path prefixes.
     */
    public function protectedPrefixes(): array
    {
        return $this->protectedPrefixes;
    }

    /**
     * @return string When the app booted to serve this request.
     */
    public function bootedAt(): string
    {
        return $this->bootedAt;
    }

    /**
     * @return string The app's root directory.
     */
    public function root(): string
    {
        return $this->root;
    }
}
