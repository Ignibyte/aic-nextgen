<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Exception\ContainerException;
use AIC\Database\Contracts\Database;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use LogicException;

/**
 * What the generated console wiring (`generated/`, #93) builds the modules'
 * commands and jobs from: the roots a console constructor may ask for. The
 * console reads no request, so it has no response writer or boot time, and it
 * is the only side that sends mail. Its root, which the stored files (#213)
 * are under, is what `bin/aic` passes the console kernel.
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
     * @param string        $root          The app's root directory; empty when `bin/aic` names none.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
        private Plugins $plugins,
        private string $root = '',
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
     * @throws LogicException When `bin/aic` gave the console kernel no root.
     *
     * @return string The app's root directory, which a command's or job's stored files (#213) are under.
     */
    public function root(): string
    {
        return $this->root === ''
            ? throw new LogicException("A command or job asks for the app's root, and bin/aic gives ConsoleKernel none.")
            : $this->root;
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
