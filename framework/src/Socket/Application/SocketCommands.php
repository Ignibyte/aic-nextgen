<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Access\Application\PostgresRoles;
use AIC\Controller\Contracts\Command;
use AIC\Database\Contracts\Database;
use AIC\Session\Application\PostgresSessionStore;
use AIC\Socket\Domain\SocketConfig;
use AIC\Socket\Domain\SocketEntry;

/**
 * The socket server's command (#32), built over the console's database: its
 * sessions, roles and broadcasts.
 */
final readonly class SocketCommands
{
    /**
     * @param Database     $database     The database.
     * @param SocketConfig $socketConfig How the server listens.
     */
    public function __construct(
        private Database $database,
        private SocketConfig $socketConfig,
    ) {
    }

    /**
     * @param array<string, SocketEntry> $entries Every declared socket by path.
     *
     * @return array<string, Command> `socket:serve`.
     */
    public function byName(array $entries): array
    {
        $socketAdmission = new SocketAdmission(new PostgresSessionStore($this->database), new PostgresRoles($this->database));
        $socketServer = new SocketServer(
            $this->socketConfig,
            new SocketUpgrade($socketAdmission),
            new FrameDispatch($this->socketConfig, new SocketCall()),
            new SocketFanout($this->database),
        );

        return ['socket:serve' => new SocketServeCommand($socketServer, $this->socketConfig, $entries)];
    }
}
