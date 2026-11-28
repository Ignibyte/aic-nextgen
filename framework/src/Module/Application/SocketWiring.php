<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Socket\Domain\SocketEntry;

/**
 * The modules' sockets (#32), for the console kernel's `socket:serve`; the
 * generated wiring implements it when some module routes to a Socket.
 */
interface SocketWiring
{
    /**
     * @param ConsoleContext $consoleContext The console's roots.
     *
     * @return array<string, SocketEntry> The modules' sockets by path.
     */
    public function sockets(ConsoleContext $consoleContext): array;
}
