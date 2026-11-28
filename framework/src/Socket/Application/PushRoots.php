<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Broadcast\Application\Broadcaster;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Socket;
use AIC\Database\Contracts\Database;
use AIC\Module\Application\ConsoleContext;
use AIC\Module\Application\ConsoleWiring;
use AIC\Module\Application\SocketWiring;
use AIC\Socket\Domain\SocketConfig;
use AIC\Socket\Domain\SocketEntry;
use LogicException;

/**
 * The console's roots for real-time push (#32): the broadcaster a command,
 * job or socket publishes with (#314), a module's Socket wrapped as the
 * server serves it, and `socket:serve` over every module's sockets. The
 * console context hands it to the generated wiring as `push()`.
 */
final readonly class PushRoots
{
    /**
     * @param Database       $database  The database.
     * @param AppConfig|null $appConfig The app's settings; null when `bin/aic` gives none.
     */
    public function __construct(
        private Database $database,
        private ?AppConfig $appConfig,
    ) {
    }

    /**
     * @throws LogicException When `bin/aic` gave the console kernel no app config.
     *
     * @return Broadcaster Publishes to a broadcast channel.
     */
    public function broadcaster(): Broadcaster
    {
        if (!$this->appConfig instanceof AppConfig) {
            throw new LogicException('A broadcast from the console needs the app config: pass it to ConsoleKernel in bin/aic.');
        }

        return new Broadcaster($this->database, $this->appConfig);
    }

    /**
     * @param Socket $socket A module's Socket.
     * @param string $need   The permission its route needs, `signed-in`, or empty for none.
     *
     * @return SocketEntry It as the socket server serves it.
     */
    public function socket(Socket $socket, string $need = ''): SocketEntry
    {
        return new SocketEntry($socket, $need);
    }

    /**
     * @param ConsoleContext     $consoleContext The console's roots, which the modules' sockets are built from.
     * @param ConsoleWiring|null $consoleWiring  The modules' generated wiring; null for none.
     *
     * @return array<string, Command> `socket:serve` over the modules' sockets, none when no module has one.
     */
    public function commands(ConsoleContext $consoleContext, ?ConsoleWiring $consoleWiring): array
    {
        $entries = $consoleWiring instanceof SocketWiring
            ? $consoleWiring->sockets($consoleContext)
            : [];
        $socketConfig = $this->appConfig instanceof AppConfig
            ? $this->appConfig->sockets
            : new SocketConfig();

        return (new SocketCommands($this->database, $socketConfig))->byName($entries);
    }
}
