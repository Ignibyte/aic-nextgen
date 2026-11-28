<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use AIC\Socket\Domain\SocketConfig;
use AIC\Socket\Domain\SocketEntry;
use InvalidArgumentException;
use Override;

use function count;
use function ctype_digit;
use function str_starts_with;
use function stream_socket_server;
use function strlen;
use function substr;

/**
 * `bin/aic socket:serve [--host=<address>] [--port=<port>] [--seconds=<n>]`
 * (#32): serves every declared socket from one process until it is stopped,
 * or for `--seconds`; the address and port default to `AppConfig::$sockets`.
 * A proxy in front routes the sockets' paths to it.
 */
final readonly class SocketServeCommand implements Command
{
    /**
     * @param SocketServer               $socketServer The loop.
     * @param SocketConfig               $socketConfig The address it listens on by default.
     * @param array<string, SocketEntry> $entries      Every declared socket by path.
     */
    public function __construct(
        private SocketServer $socketServer,
        private SocketConfig $socketConfig,
        private array $entries,
    ) {
    }

    /**
     * @param CommandInput $commandInput The flags.
     *
     * @throws QueryException           When the database fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResult What it served; exit 1 when it cannot listen, 2 for a bad flag.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $arguments = $commandInput->arguments();
        $host = $this->flag($arguments, 'host', $this->socketConfig->host);
        $port = $this->flag($arguments, 'port', (string) $this->socketConfig->port);
        $seconds = $this->flag($arguments, 'seconds', '0');

        if (!ctype_digit($port) || !ctype_digit($seconds)) {
            return new CommandResult(['usage: bin/aic socket:serve [--host=<address>] [--port=<port>] [--seconds=<n>]'], 2);
        }

        $address = 'tcp://' . $host . ':' . $port;
        $listener = stream_socket_server($address);

        if ($listener === false) {
            return new CommandResult(['socket:serve cannot listen on ' . $address . '.'], 1);
        }

        $this->socketServer->serve([$listener], $this->entries, (int) $seconds);

        return new CommandResult(['socket:serve served ' . count($this->entries) . ' socket(s) on ' . $address . '.'], 0);
    }

    /**
     * @param list<string> $arguments The command line.
     * @param string       $name      A flag's name.
     * @param string       $default   Its value when it is not given.
     *
     * @return string Its last value.
     */
    private function flag(array $arguments, string $name, string $default): string
    {
        $prefix = '--' . $name . '=';
        $value = $default;

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, $prefix)) {
                continue;
            }

            $value = substr($argument, strlen($prefix));
        }

        return $value;
    }
}
