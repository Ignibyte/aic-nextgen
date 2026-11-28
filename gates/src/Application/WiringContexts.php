<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Broadcast\Application\Broadcaster;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageDocuments;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Contracts\PublicAssets;
use AIC\Module\Application\ConsoleContext;
use AIC\Module\Application\HandlerContext;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use AIC\Storage\Application\PageFiles;
use Gates\Domain\WiringRoots;

/**
 * The roots each side of the app gives the modules' constructors (#93), as the
 * generated code reaches them: the web side's through `HandlerContext`, the
 * console's through `ConsoleContext`. They match the contexts' accessors; a
 * path (`outbound()->client`, `roots()->root`) reaches one through a bundle
 * the context returns (#305).
 */
final readonly class WiringContexts
{
    /**
     * @param OutboundRoots $outboundRoots The outbound roots both sides give (#305).
     */
    public function __construct(
        private OutboundRoots $outboundRoots = new OutboundRoots(),
    ) {
    }

    /**
     * @return WiringRoots What a controller or a middleware may ask for.
     */
    public function web(): WiringRoots
    {
        return new WiringRoots(
            HandlerContext::class,
            'handlerContext',
            [
                AppConfig::class => 'appConfig',
                Database::class => 'database',
                EnvelopeCodec::class => 'envelopeCodec',
                PageDocuments::class => 'documents',
                PublicAssets::class => 'assets',
                ResponseBuilder::class => 'responseBuilder',
                ...$this->outboundRoots->byType('roots()->outbound()->'),
            ],
            [
                'bootedAt' => 'roots()->bootedAt',
                'protectedPrefixes' => 'roots()->protectedPrefixes',
                'root' => 'roots()->root',
                'schedules' => 'roots()->schedules',
            ],
        );
    }

    /**
     * @return WiringRoots What a command or a job may ask for.
     */
    public function console(): WiringRoots
    {
        return new WiringRoots(
            ConsoleContext::class,
            'consoleContext',
            [
                Broadcaster::class => 'push()->broadcaster',
                Database::class => 'database',
                MailConfig::class => 'mailConfig',
                MailTransport::class => 'mailTransport',
                PageDocuments::class => 'documents',
                PageFiles::class => 'pageFiles',
                ...$this->outboundRoots->byType('outbound()->'),
            ],
            ['root' => 'root'],
        );
    }
}
