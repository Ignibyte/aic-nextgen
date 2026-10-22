<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Exception\ContainerException;
use AIC\Database\Contracts\Database;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;

/**
 * What the generated console wiring (`generated/`, #93) builds the modules'
 * commands and jobs from: the roots a console constructor may ask for. The
 * console reads no request, so it has no response writer or boot time, and it
 * is the only side that sends mail.
 *
 * @api
 */
final readonly class ConsoleContext
{
    /**
     * @param Database      $database      The database.
     * @param MailTransport $mailTransport Sends mail.
     * @param MailConfig    $mailConfig    The mail settings.
     * @param Plugins       $plugins       The plugins, by type.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
        private Plugins $plugins,
    ) {
    }

    /**
     * @return Database The database.
     */
    public function database(): Database
    {
        return $this->database;
    }

    /**
     * @return MailTransport Sends mail.
     */
    public function mailTransport(): MailTransport
    {
        return $this->mailTransport;
    }

    /**
     * @return MailConfig The mail settings.
     */
    public function mailConfig(): MailConfig
    {
        return $this->mailConfig;
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
        return $this->plugins->tagged($type);
    }
}
